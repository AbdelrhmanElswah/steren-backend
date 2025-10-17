<?php

namespace App\Http\Controllers\Api\Odoo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Odoo\StoreCarouselRequest;
use App\Http\Requests\Api\Odoo\UpdateCarouselRequest;
use App\Http\Requests\Api\Odoo\GetCarouselsRequest;
use App\Http\Resources\Api\Odoo\CarouselResource;
use App\Services\Odoo\CarouselService;
use App\Traits\ApiResponse;
use App\Traits\OdooFetchTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CarouselController extends Controller
{
    use ApiResponse, OdooFetchTrait;

    public function __construct(
        private CarouselService $carouselService
    ) {}

    public function store(StoreCarouselRequest $request): JsonResponse
    {
        try {
            $carousel = $this->carouselService->create($request->validated());
            $carousel->load('media');

            return $this->createdResponse(
                new CarouselResource($carousel),
                'Carousel created successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarouselController: Error creating carousel', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to create carousel: ' . $e->getMessage(), 500);
        }
    }

    public function updateByOdooId(UpdateCarouselRequest $request, string $odooId): JsonResponse
    {
        try {
            $carousel = $this->carouselService->updateByOdooId($odooId, $request->validated());
            $carousel->load('media');

            return $this->successResponse(
                new CarouselResource($carousel),
                'Carousel updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarouselController: Error updating carousel', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId,
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to update carousel: ' . $e->getMessage(), 500);
        }
    }

    public function deleteByOdooId(string $odooId): JsonResponse
    {
        try {
            $this->carouselService->deleteByOdooId($odooId);

            return $this->noContentResponse('Carousel deleted successfully');
        } catch (\Exception $e) {
            Log::error('CarouselController: Error deleting carousel', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to delete carousel: ' . $e->getMessage(), 500);
        }
    }

    public function restoreByOdooId(string $odooId): JsonResponse
    {
        try {
            $carousel = $this->carouselService->restoreByOdooId($odooId);
            $carousel->load('media');

            return $this->successResponse(
                new CarouselResource($carousel),
                'Carousel restored successfully'
            );
        } catch (\Exception $e) {
            Log::error('CarouselController: Error restoring carousel', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'odoo_id' => $odooId
            ]);

            return $this->errorResponse('Failed to restore carousel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch carousels from regular database for user end
     */
    public function getCarousels(GetCarouselsRequest $request): JsonResponse
    {
        try {
            $query = \App\Models\Carousel::with('media')
                ->when($request->filled('active_only'), function ($query) {
                    return $query->active()->withinDateRange();
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('title', 'like', '%' . $request->search . '%')
                                ->orWhere('description', 'like', '%' . $request->search . '%');
                })
                ->when($request->filled('id'), function ($query) use ($request) {
                    return $query->where('id', $request->id);
                })
                ->when($request->filled('odoo_id'), function ($query) use ($request) {
                    return $query->where('odoo_id', $request->odoo_id);
                });

            // Default ordering
            if (!$request->filled('sort_by')) {
                $query->ordered();
            } else {
                $sortBy = $request->sort_by;
                $sortOrder = $request->get('sort_order', 'asc');
                
                switch ($sortBy) {
                    case 'title':
                        $query->orderBy('title', $sortOrder);
                        break;
                    case 'start_date':
                        $query->orderBy('start_date', $sortOrder);
                        break;
                    case 'end_date':
                        $query->orderBy('end_date', $sortOrder);
                        break;
                    case 'created_at':
                        $query->orderBy('created_at', $sortOrder);
                        break;
                    default:
                        $query->ordered();
                }
            }

            $carousels = $query->get();

            $collection = CarouselResource::collection($carousels);
            return $collection->additional([
                'success' => true,
                'message' => 'Carousels retrieved successfully',
                'total' => $carousels->count()
            ])->response();

        } catch (\Exception $e) {
            Log::error('CarouselController: Error fetching carousels', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Failed to fetch carousels: ' . $e->getMessage(), 500);
        }
    }
}
