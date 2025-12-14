<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\ExteriorColor;
use App\Traits\ApiResponse;
use App\Traits\LocalizedResponse;
use Illuminate\Http\JsonResponse;

class ExteriorColorOptionsController extends Controller
{
    use ApiResponse, LocalizedResponse;

    /**
     * Get all exterior colors as options
     */
    public function getExteriorColorOptions(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $lang = $this->getLanguageFromRequest($request);
            
            $colors = ExteriorColor::select('id', 'name', 'name_ar', 'name_kr')
                ->orderBy('name')
                ->get();
            
            $options = $colors->map(function ($color) use ($lang) {
                return [
                    'value' => $color->id,
                    'label' => $this->getLocalizedField($color, 'name', $lang),
                ];
            });

            return $this->successResponse($options, 'Exterior color options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve exterior color options: ' . $e->getMessage(), 500);
        }
    }
}
