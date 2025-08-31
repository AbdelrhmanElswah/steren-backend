<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\ExteriorColor;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ExteriorColorOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all exterior colors as options
     */
    public function getExteriorColorOptions(): JsonResponse
    {
        try {
            $colors = ExteriorColor::orderBy('name')->get();
            
            $options = $colors->map(function ($color) {
                return [
                    'value' => $color->id,
                    'label' => $color->name,
                    'hex_code' => $color->hex_code,
                ];
            });

            return $this->successResponse($options, 'Exterior color options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve exterior color options: ' . $e->getMessage(), 500);
        }
    }
}
