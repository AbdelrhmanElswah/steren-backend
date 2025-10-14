<?php

namespace App\Http\Controllers;

use App\Http\Requests\VinDecodeRequest;
use App\Http\Resources\VinDecoderResource;
use App\Services\VehicleDatabasesService;
use App\Exceptions\VehicleDatabasesException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VinDecoderController extends Controller
{
    use ApiResponse;

    protected VehicleDatabasesService $vehicleDatabasesService;

    public function __construct(VehicleDatabasesService $vehicleDatabasesService)
    {
        $this->vehicleDatabasesService = $vehicleDatabasesService;
    }

    /**
     * Decode VIN using Vehicle Databases API
     *
     * @param VinDecodeRequest $request
     * @return JsonResponse
     */
    public function decode(VinDecodeRequest $request): JsonResponse
    {
        try {
            $vin = $request->validated()['vin'];
            $cache = $request->validated()['cache'] ?? true;
            $saveToDatabase = $request->validated()['save_to_database'] ?? false;

            if ($saveToDatabase) {
                $result = $this->vehicleDatabasesService->decodeAndSaveVin($vin, true);
                
                return $this->successResponse(
                    new VinDecoderResource($result),
                    'VIN decoded and saved successfully'
                );
            }

            $result = $this->vehicleDatabasesService->decodeVin($vin, $cache);
            
            return $this->successResponse($result['data'], 'VIN decoded successfully');

        } catch (VehicleDatabasesException $e) {
            Log::error('VIN decode failed', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN decode', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while decoding the VIN',
                500
            );
        }
    }

    /**
     * Get VIN information from database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVinInfo(Request $request): JsonResponse
    {
        $vin = $request->input('vin');
        
        if (!$vin) {
            return $this->errorResponse('VIN is required', 400);
        }

        try {
            $vinDecoder = \App\Models\VinDecoder::byVin($vin)->first();
            
            if (!$vinDecoder) {
                return $this->errorResponse('VIN not found in database', 404);
            }

            return $this->successResponse(
                new VinDecoderResource($vinDecoder),
                'VIN information retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving VIN info', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving VIN information',
                500
            );
        }
    }

    /**
     * Get service status
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->vehicleDatabasesService->getStatus();
            
            return $this->successResponse($status, 'Service status retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error getting service status', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving service status',
                500
            );
        }
    }
}
