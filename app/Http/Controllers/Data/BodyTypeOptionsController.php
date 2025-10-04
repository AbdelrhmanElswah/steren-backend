<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Services\BodyTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BodyTypeOptionsController extends Controller
{
    use ApiResponse;

    protected BodyTypeService $bodyTypeService;

    public function __construct(BodyTypeService $bodyTypeService)
    {
        $this->bodyTypeService = $bodyTypeService;
    }

    public function getBodyTypeOptions(): JsonResponse
    {
        try {
            $bodyTypes = $this->bodyTypeService->getBodyTypeOptions();
            $options = $this->bodyTypeService->toOptionResourceCollection($bodyTypes);

            return $this->successResponse($options, 'Body type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve body type options: ' . $e->getMessage(), 500);
        }
    }
}
