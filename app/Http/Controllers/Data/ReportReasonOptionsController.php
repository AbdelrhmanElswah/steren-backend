<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportReasonOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get report reason options
     */
    public function getReportReasonOptions(): JsonResponse
    {
        try {
            $options = [
                ['value' => 'inappropriate_content', 'label' => 'Inappropriate Content'],
                ['value' => 'spam', 'label' => 'Spam'],
                ['value' => 'fraudulent', 'label' => 'Fraudulent'],
                ['value' => 'duplicate', 'label' => 'Duplicate Listing'],
                ['value' => 'wrong_category', 'label' => 'Wrong Category'],
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'other', 'label' => 'Other'],
            ];

            return $this->successResponse($options, 'Report reason options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve report reason options: ' . $e->getMessage(), 500);
        }
    }
}
