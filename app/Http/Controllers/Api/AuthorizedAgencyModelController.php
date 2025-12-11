<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthorizedAgencyModel\StoreAuthorizedAgencyModelRequest;
use App\Http\Requests\Api\AuthorizedAgencyModel\UpdateAuthorizedAgencyModelRequest;
use App\Http\Resources\Api\AuthorizedAgencyModelResource;
use App\Models\AuthorizedAgency;
use App\Models\AuthorizedAgencyModel;
use App\Services\AuthorizedAgencyModelService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class AuthorizedAgencyModelController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthorizedAgencyModelService $authorizedAgencyModelService
    ) {}

    public function index(AuthorizedAgency $authorizedAgency): JsonResponse
    {
        try {
            $models = $authorizedAgency->models()
                ->with(['outerImage', 'innerImages', 'carModel.brand'])
                ->get();

            return $this->successResponse(
                AuthorizedAgencyModelResource::collection($models),
                'Authorized agency models fetched successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyModelController@index error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'authorized_agency_id' => $authorizedAgency->id,
            ]);

            return $this->errorResponse('Failed to fetch authorized agency models', 500);
        }
    }

    public function show(AuthorizedAgencyModel $authorizedAgencyModel): JsonResponse
    {
        try {
            $authorizedAgencyModel->load(['outerImage', 'innerImages', 'carModel.brand', 'authorizedAgency']);

            // Get trims from car listings for this agency's studio and model
            $trims = $this->getTrimsForModel($authorizedAgencyModel);

            $resource = new AuthorizedAgencyModelResource($authorizedAgencyModel);
            $data = $resource->toArray(request());
            $data['trims'] = $trims;

            return $this->successResponse(
                $data,
                'Authorized agency model fetched successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyModelController@show error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'authorized_agency_model_id' => $authorizedAgencyModel->id,
            ]);

            return $this->errorResponse('Failed to fetch authorized agency model', 500);
        }
    }

    /**
     * Get trims from car listings for the agency's studio and model
     */
    private function getTrimsForModel(AuthorizedAgencyModel $model): array
    {
        $agency = $model->authorizedAgency;
        if (!$agency || !$agency->partner_id) {
            return [];
        }

        // Get studio IDs for the agency
        $userIds = \App\Models\User::where('partner_id', $agency->partner_id)->pluck('id');
        if ($userIds->isEmpty()) {
            return [];
        }

        $studioIds = \App\Models\DealerStudio::whereIn('dealer_id', $userIds)->pluck('id')->all();
        if (empty($studioIds)) {
            return [];
        }

        // Query car listings for this studio and model
        $listings = \App\Models\CarListing::whereIn('studio_id', $studioIds)
            ->where('car_model_id', $model->car_model_id)
            ->where('status', 'active')
            ->whereNull('sold_at')
            ->with('brand:id,name')
            ->select('id', 'trim', 'engine_size', 'price', 'price_currency', 'car_brand_id')
            ->get();

        return $listings->map(function ($listing) {
            return [
                'listing_id' => $listing->id,
                'trim_name' => $listing->trim ?? 'Base',
                'brand_name' => $listing->brand->name ?? null,
                'engine_size' => $listing->engine_size,
                'price' => $listing->price,
                'price_currency' => $listing->price_currency ?? 'USD',
            ];
        })->values()->toArray();
    }

    public function store(StoreAuthorizedAgencyModelRequest $request, AuthorizedAgency $authorizedAgency): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['authorized_agency_id'] = $authorizedAgency->id;

            $model = $this->authorizedAgencyModelService->create(
                $data,
                $request->file('outer_image'),
                $request->input('outer_image_base64'),
                $request->file('inner_images', []),
                $request->input('inner_images_base64', [])
            );

            return $this->createdResponse(
                new AuthorizedAgencyModelResource($model),
                'Authorized agency model created successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyModelController@store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'authorized_agency_id' => $authorizedAgency->id,
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to create authorized agency model: ' . $e->getMessage(), 500);
        }
    }

    public function update(UpdateAuthorizedAgencyModelRequest $request, AuthorizedAgencyModel $authorizedAgencyModel): JsonResponse
    {
        try {
            $data = $request->validated();

            if (isset($data['authorized_agency_id']) && $data['authorized_agency_id'] !== $authorizedAgencyModel->authorized_agency_id) {
                return $this->errorResponse('authorized_agency_id cannot be changed for this record', 422);
            }

            $model = $this->authorizedAgencyModelService->update(
                $authorizedAgencyModel,
                $data,
                $request->file('outer_image'),
                $request->input('outer_image_base64'),
                $request->file('inner_images', []),
                $request->input('inner_images_base64', []),
                $request->input('existing_outer_image_url'),
                $request->input('existing_inner_image_urls', [])
            );

            return $this->successResponse(
                new AuthorizedAgencyModelResource($model),
                'Authorized agency model updated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyModelController@update error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'authorized_agency_model_id' => $authorizedAgencyModel->id,
                'payload' => $request->validated(),
            ]);

            return $this->errorResponse('Failed to update authorized agency model: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an authorized agency model (soft delete)
     */
    public function destroy(AuthorizedAgencyModel $authorizedAgencyModel): JsonResponse
    {
        try {
            $authorizedAgencyModel->delete();

            return $this->noContentResponse('Authorized agency model deleted successfully');
        } catch (\Throwable $e) {
            Log::error('AuthorizedAgencyModelController@destroy error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'authorized_agency_model_id' => $authorizedAgencyModel->id,
            ]);

            return $this->errorResponse('Failed to delete authorized agency model: ' . $e->getMessage(), 500);
        }
    }
}


