<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Services\CarouselService;
use App\Http\Requests\Carousel\GetCarouselsRequest;
use App\Http\Requests\Carousel\GetAllCarouselsRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CarouselController extends Controller
{
    use ApiResponse;

    protected CarouselService $carouselService;

    public function __construct(CarouselService $carouselService)
    {
        $this->carouselService = $carouselService;
    }

    public function getCarousels(GetCarouselsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $carousels = $this->carouselService->getActiveCarousels($validated['limit'] ?? null);
            $data = $this->carouselService->toResourceCollection($carousels);

            return $this->successResponse($data, 'Carousels retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousels: ' . $e->getMessage(), 500);
        }
    }

    public function getAllCarousels(GetAllCarouselsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $filters = [
                'active_only' => $validated['active_only'] ?? false,
                'search' => $validated['search'] ?? null,
            ];

            $carousels = $this->carouselService->getAllCarousels($filters);
            $data = $this->carouselService->toResourceCollection($carousels);

            return $this->successResponse($data, 'All carousels retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousels: ' . $e->getMessage(), 500);
        }
    }

    public function getCarouselById($id): JsonResponse
    {
        try {
            $carousel = $this->carouselService->getCarouselByOdooId($id);

            if (!$carousel) {
                return $this->notFoundResponse('Carousel not found');
            }

            $data = $this->carouselService->toResource($carousel);

            return $this->successResponse($data, 'Carousel retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousel: ' . $e->getMessage(), 500);
        }
    }
}
