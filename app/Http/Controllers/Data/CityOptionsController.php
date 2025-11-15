<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all cities as options
     */
    public function getCityOptions(Request $request): JsonResponse
    {
        try {
            $query = City::orderBy('name');
            
            $cities = $query->get();
            
            $options = $cities->map(function ($city) {
                return [
                    'value' => $city->id,
                    'label' => $city->name,
                ];
            });

            return $this->successResponse($options, 'City options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve city options: ' . $e->getMessage(), 500);
        }
    }
}
