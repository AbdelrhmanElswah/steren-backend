<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\CarCondition;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CarConditionOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all car conditions as options
     */
    public function getCarConditionOptions(): JsonResponse
    {
        try {
            $conditions = CarCondition::orderBy('name')->get();
            
            $options = $conditions->map(function ($condition) {
                return [
                    'value' => $condition->id,
                    'label' => $condition->name,
                ];
            });

            return $this->successResponse($options, 'Car condition options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car condition options: ' . $e->getMessage(), 500);
        }
    }
}
