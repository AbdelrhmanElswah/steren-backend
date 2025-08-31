<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarModelOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get car models as options (optionally filtered by brand)
     */
    public function getCarModelOptions(Request $request): JsonResponse
    {
        try {
            $query = CarModel::with('brand')->orderBy('name');
            
            if ($request->has('brand_id')) {
                $query->where('car_brand_id', $request->brand_id);
            }
            
            $models = $query->get();
            
            $options = $models->map(function ($model) {
                return [
                    'value' => $model->id,
                    'label' => $model->name,
                    'brand_id' => $model->car_brand_id,
                    'brand_name' => $model->brand->name,
                ];
            });

            return $this->successResponse($options, 'Car model options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car model options: ' . $e->getMessage(), 500);
        }
    }
}
