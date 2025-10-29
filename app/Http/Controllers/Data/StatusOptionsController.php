<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Status;
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

    /**
     * Get dealer application status options
     */
    public function getDealerApplicationStatusOptions(): JsonResponse
    {
        try {
            $statuses = Status::dealerApplicationStatuses();
            
            $options = $statuses->map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'display_name' => $status->display_name,
                    'description' => $status->description,
                    'color' => $status->color,
                    'sort_order' => $status->sort_order,
                    'is_active' => $status->is_active,
                ];
            });

            return $this->successResponse($options, 'Dealer application status options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dealer application status options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all status types
     */
    public function getAllStatusTypes(): JsonResponse
    {
        try {
            $types = [
                'dealer_application' => Status::dealerApplicationStatuses(),
                'car_listing' => Status::carListingStatuses(),
                'user' => Status::userStatuses(),
                'general' => Status::generalStatuses(),
            ];

            $formattedTypes = [];
            foreach ($types as $type => $statuses) {
                $formattedTypes[$type] = $statuses->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'name' => $status->name,
                        'display_name' => $status->display_name,
                        'description' => $status->description,
                        'color' => $status->color,
                        'sort_order' => $status->sort_order,
                        'is_active' => $status->is_active,
                    ];
                });
            }

            return $this->successResponse($formattedTypes, 'All status types retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve status types: ' . $e->getMessage(), 500);
        }
    }
}
