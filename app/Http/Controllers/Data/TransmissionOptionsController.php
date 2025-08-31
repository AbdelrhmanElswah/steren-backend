<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Transmission;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TransmissionOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all transmission types as options
     */
    public function getTransmissionOptions(): JsonResponse
    {
        try {
            $transmissions = Transmission::orderBy('name')->get();
            
            $options = $transmissions->map(function ($transmission) {
                return [
                    'value' => $transmission->id,
                    'label' => $transmission->name,
                ];
            });

            return $this->successResponse($options, 'Transmission options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transmission options: ' . $e->getMessage(), 500);
        }
    }
}
