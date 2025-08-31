<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PromotionTypeOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get promotion type options
     */
    public function getPromotionTypeOptions(): JsonResponse
    {
        try {
            $options = [
                ['value' => 'banner', 'label' => 'Banner'],
                ['value' => 'carousel', 'label' => 'Carousel'],
                ['value' => 'popup', 'label' => 'Popup'],
            ];

            return $this->successResponse($options, 'Promotion type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve promotion type options: ' . $e->getMessage(), 500);
        }
    }
}
