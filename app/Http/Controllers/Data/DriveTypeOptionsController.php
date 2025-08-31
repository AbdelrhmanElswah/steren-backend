<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\DriveType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DriveTypeOptionsController extends Controller
{
    use ApiResponse;

    /**
     * Get all drive types as options
     */
    public function getDriveTypeOptions(): JsonResponse
    {
        try {
            $driveTypes = DriveType::orderBy('name')->get();
            
            $options = $driveTypes->map(function ($driveType) {
                return [
                    'value' => $driveType->id,
                    'label' => $driveType->name,
                ];
            });

            return $this->successResponse($options, 'Drive type options retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve drive type options: ' . $e->getMessage(), 500);
        }
    }
}
