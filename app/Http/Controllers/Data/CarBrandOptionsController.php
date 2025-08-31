<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CarBrandOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all car brands as options
     */
    public function getCarBrandOptions(): JsonResponse
    {
        try {
            $brands = CarBrand::orderBy('name')->get();
            
            $options = $brands->map(function ($brand) {
                return [
                    'value' => $brand->id,
                    'label' => $brand->name,
                    'logo_url' => $brand->logo_url,
                ];
            });

            return $this->successResponse($options, 'Car brand options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car brand options: ' . $e->getMessage(), 500);
        }
    }
}
