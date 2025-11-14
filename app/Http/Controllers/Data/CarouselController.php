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
            $limit = $validated['limit'] ?? null;
            
            // Build cache key based on request parameters
            $cacheKey = 'carousels_active_' . ($limit ?? 'all');
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($limit) {
                // Only select needed columns and eager load media with limited columns
                $carousels = \App\Models\Carousel::select('id', 'odoo_id', 'title', 'description', 'button_text', 'link_url', 'sort_order')
                    ->with(['medias' => function($query) {
                        $query->select('id', 'modelable_type', 'modelable_id', 'media', 'path_file_name', 'file_type', 'info', 'order')
                            ->where('info', 'main')
                            ->limit(1);
                    }])
                    ->active()
                    ->withinDateRange()
                    ->ordered()
                    ->when($limit, function($query) use ($limit) {
                        $query->limit($limit);
                    })
                    ->get();
                
                // Build response directly without using resource (faster, avoids temporary URL generation)
                $carouselsCollection = $carousels->map(function ($carousel) {
                    $mediaLink = null;
                    if ($carousel->medias->isNotEmpty()) {
                        $firstMedia = $carousel->medias->first();
                        if ($firstMedia && $firstMedia->path_file_name) {
                            // Use public URL (already optimized, no S3 API calls)
                            $mediaLink = $firstMedia->getS3PublicUrl($firstMedia->path_file_name);
                        }
                    }

                    return [
                        'id' => $carousel->odoo_id,
                        'title' => $carousel->title,
                        'description' => $carousel->description,
                        'button_text' => $carousel->button_text,
                        'link_url' => $carousel->link_url,
                        'sort_order' => $carousel->sort_order,
                        'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                    ];
                })->toArray();

                return [
                    'data' => $carouselsCollection,
                    'total' => count($carouselsCollection),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Carousels retrieved successfully',
                'data' => $result['data'],
                'total' => $result['total'],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CarouselController: Error fetching carousels', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);
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
            
            // Build cache key based on filters
            $cacheKey = 'carousels_all_' . md5(serialize($filters));
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($filters) {
                // Only select needed columns and eager load media with limited columns
                $query = \App\Models\Carousel::select('id', 'odoo_id', 'title', 'description', 'button_text', 'link_url', 'sort_order')
                    ->with(['medias' => function($q) {
                        $q->select('id', 'modelable_type', 'modelable_id', 'media', 'path_file_name', 'file_type', 'info', 'order')
                            ->where('info', 'main')
                            ->limit(1);
                    }]);
                
                if (isset($filters['active_only']) && $filters['active_only']) {
                    $query->active()->withinDateRange();
                }
                
                if (isset($filters['search']) && $filters['search']) {
                    $query->where(function ($q) use ($filters) {
                        $q->where('title', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('description', 'like', '%' . $filters['search'] . '%');
                    });
                }
                
                $carousels = $query->ordered()->get();
                
                // Build response directly without using resource (faster, avoids temporary URL generation)
                $carouselsCollection = $carousels->map(function ($carousel) {
                    $mediaLink = null;
                    if ($carousel->medias->isNotEmpty()) {
                        $firstMedia = $carousel->medias->first();
                        if ($firstMedia && $firstMedia->path_file_name) {
                            // Use public URL (already optimized, no S3 API calls)
                            $mediaLink = $firstMedia->getS3PublicUrl($firstMedia->path_file_name);
                        }
                    }

                    return [
                        'id' => $carousel->odoo_id,
                        'title' => $carousel->title,
                        'description' => $carousel->description,
                        'button_text' => $carousel->button_text,
                        'link_url' => $carousel->link_url,
                        'sort_order' => $carousel->sort_order,
                        'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                    ];
                })->toArray();

                return [
                    'data' => $carouselsCollection,
                    'total' => count($carouselsCollection),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All carousels retrieved successfully',
                'data' => $result['data'],
                'total' => $result['total'],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CarouselController: Error fetching all carousels', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);
            return $this->errorResponse('Failed to retrieve carousels: ' . $e->getMessage(), 500);
        }
    }

    public function getCarouselById($id): JsonResponse
    {
        try {
            // Build cache key based on ID
            $cacheKey = 'carousel_' . $id;
            
            // Cache for 24 hours (86400 seconds)
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($id) {
                $carousel = \App\Models\Carousel::select('id', 'odoo_id', 'title', 'description', 'button_text', 'link_url', 'sort_order')
                    ->with(['medias' => function($query) {
                        $query->select('id', 'modelable_type', 'modelable_id', 'media', 'path_file_name', 'file_type', 'info', 'order')
                            ->where('info', 'main')
                            ->limit(1);
                    }])
                    ->where('odoo_id', $id)
                    ->first();

                if (!$carousel) {
                    return null;
                }
                
                // Build response directly without using resource (faster, avoids temporary URL generation)
                $mediaLink = null;
                if ($carousel->medias->isNotEmpty()) {
                    $firstMedia = $carousel->medias->first();
                    if ($firstMedia && $firstMedia->path_file_name) {
                        // Use public URL (already optimized, no S3 API calls)
                        $mediaLink = $firstMedia->getS3PublicUrl($firstMedia->path_file_name);
                    }
                }

                return [
                    'id' => $carousel->odoo_id,
                    'title' => $carousel->title,
                    'description' => $carousel->description,
                    'button_text' => $carousel->button_text,
                    'link_url' => $carousel->link_url,
                    'sort_order' => $carousel->sort_order,
                    'media' => $mediaLink ? ['media_link' => $mediaLink] : null,
                ];
            });
            
            if ($result === null) {
                return $this->notFoundResponse('Carousel not found');
            }

            return $this->successResponse($result, 'Carousel retrieved successfully');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CarouselController: Error fetching carousel by ID', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);
            return $this->errorResponse('Failed to retrieve carousel: ' . $e->getMessage(), 500);
        }
    }
}
