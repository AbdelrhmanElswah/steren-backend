<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConditionTypeOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get condition type options
     */
    public function getConditionTypeOptions(): JsonResponse
    {
        try {
            $options = [
                ['value' => 'new', 'label' => 'New'],
                ['value' => 'used', 'label' => 'Used'],
            ];

            return $this->successResponse($options, 'Condition type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve condition type options: ' . $e->getMessage(), 500);
        }
    }
}
