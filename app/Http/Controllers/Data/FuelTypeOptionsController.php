<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\FuelType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class FuelTypeOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all fuel types as options
     */
    public function getFuelTypeOptions(): JsonResponse
    {
        try {
            $fuelTypes = FuelType::orderBy('name')->get();
            
            $options = $fuelTypes->map(function ($fuelType) {
                return [
                    'value' => $fuelType->id,
                    'label' => $fuelType->name,
                ];
            });

            return $this->successResponse($options, 'Fuel type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve fuel type options: ' . $e->getMessage(), 500);
        }
    }
}
