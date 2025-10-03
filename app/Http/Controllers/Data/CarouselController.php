<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Carousel;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CarouselController extends Controller
{
    use ApiResponse;

    /**
     * Get active carousels for frontend
     */
    public function getCarousels(Request $request): JsonResponse
    {
        try {
            $query = Carousel::with('media')
                ->active()
                ->withinDateRange()
                ->ordered();

            // Optional: Filter by specific criteria
            if ($request->filled('limit')) {
                $query->limit($request->get('limit', 10));
            }

            $carousels = $query->get();

            // Transform to match frontend needs
            $data = $carousels->map(function ($carousel) {
                return [
                    'id' => $carousel->odoo_id,
                    'title' => $carousel->title,
                    'description' => $carousel->description,
                    'button_text' => $carousel->button_text,
                    'start_date' => $carousel->start_date?->format('Y-m-d'),
                    'end_date' => $carousel->end_date?->format('Y-m-d'),
                    'img' => $carousel->media ? asset('storage/' . $carousel->media->file_path) : null,
                    'image_alt' => $carousel->media?->alt_text,
                ];
            });

            return $this->successResponse($data, 'Carousels retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousels: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all carousels (including inactive) - for admin
     */
    public function getAllCarousels(Request $request): JsonResponse
    {
        try {
            $query = Carousel::with('media')
                ->when($request->filled('active_only'), function ($query) {
                    return $query->active()->withinDateRange();
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('title', 'like', '%' . $request->search . '%')
                                ->orWhere('description', 'like', '%' . $request->search . '%');
                })
                ->ordered();

            $carousels = $query->get();

            $data = $carousels->map(function ($carousel) {
                return [
                    'id' => $carousel->odoo_id,
                    'title' => $carousel->title,
                    'description' => $carousel->description,
                    'button_text' => $carousel->button_text,
                    'start_date' => $carousel->start_date?->format('Y-m-d'),
                    'end_date' => $carousel->end_date?->format('Y-m-d'),
                    'is_active' => $carousel->is_active,
                    'is_currently_active' => $carousel->isCurrentlyActive(),
                    'img' => $carousel->media ? asset('storage/' . $carousel->media->file_path) : null,
                    'image_alt' => $carousel->media?->alt_text,
                    'created_at' => $carousel->created_at?->toISOString(),
                    'updated_at' => $carousel->updated_at?->toISOString(),
                ];
            });

            return $this->successResponse($data, 'All carousels retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousels: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get carousel by ID
     */
    public function getCarouselById(Request $request, $id): JsonResponse
    {
        try {
            $carousel = Carousel::with('media')
                ->where('odoo_id', $id)
                ->first();

            if (!$carousel) {
                return $this->notFoundResponse('Carousel not found');
            }

            $data = [
                'id' => $carousel->odoo_id,
                'title' => $carousel->title,
                'description' => $carousel->description,
                'button_text' => $carousel->button_text,
                'start_date' => $carousel->start_date?->format('Y-m-d'),
                'end_date' => $carousel->end_date?->format('Y-m-d'),
                'is_active' => $carousel->is_active,
                'is_currently_active' => $carousel->isCurrentlyActive(),
                'img' => $carousel->media ? asset('storage/' . $carousel->media->file_path) : null,
                'image_alt' => $carousel->media?->alt_text,
                'created_at' => $carousel->created_at?->toISOString(),
                'updated_at' => $carousel->updated_at?->toISOString(),
            ];

            return $this->successResponse($data, 'Carousel retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve carousel: ' . $e->getMessage(), 500);
        }
    }
}
