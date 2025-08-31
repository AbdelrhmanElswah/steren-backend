<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\BodyType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BodyTypeOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all body types as options
     */
    public function getBodyTypeOptions(): JsonResponse
    {
        try {
            $bodyTypes = BodyType::orderBy('name')->get();
            
            $options = $bodyTypes->map(function ($bodyType) {
                return [
                    'value' => $bodyType->id,
                    'label' => $bodyType->name,
                    'icon_url' => $bodyType->icon_url,
                ];
            });

            return $this->successResponse($options, 'Body type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve body type options: ' . $e->getMessage(), 500);
        }
    }
}
