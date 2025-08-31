<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\ContactMethod;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactMethodController extends Controller
{
    use ApiResponse;

    /**
     * Get all active contact methods
     */
    public function getContactMethods(): JsonResponse
    {
        try {
            $contactMethods = ContactMethod::active()->ordered()->get();

            return $this->successResponse($contactMethods, 'Contact methods retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve contact methods: ' . $e->getMessage(), 500);
        }
    }
}
