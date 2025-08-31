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
     * Get all cities as options (optionally filtered by country)
     */
    public function getCityOptions(Request $request): JsonResponse
    {
        try {
            $query = City::with('country')->orderBy('name');
            
            if ($request->has('country_id')) {
                $query->where('country_id', $request->country_id);
            }
            
            $cities = $query->get();
            
            $options = $cities->map(function ($city) {
                return [
                    'value' => $city->id,
                    'label' => $city->name,
                    'country_id' => $city->country_id,
                    'country_name' => $city->country->name,
                ];
            });

            return $this->successResponse($options, 'City options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve city options: ' . $e->getMessage(), 500);
        }
    }
}
