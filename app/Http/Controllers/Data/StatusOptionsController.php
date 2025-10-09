<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StatusOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get status options for car listings
     */
    public function getStatusOptions(): JsonResponse
    {
        try {
            $options = [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'sold', 'label' => 'Sold'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'boosted', 'label' => 'Boosted'],
                ['value' => 'archive', 'label' => 'Archived'],
                ['value' => 'suspend', 'label' => 'Suspended'],
            ];

            return $this->successResponse($options, 'Status options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve status options: ' . $e->getMessage(), 500);
        }
    }
}
