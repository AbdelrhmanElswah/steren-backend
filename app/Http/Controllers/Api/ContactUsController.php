<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactUsRequest;
use App\Models\ContactUs;
use App\Services\OdooService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactUsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OdooService $odooService
    ) {}

    /**
     * Store a new contact us submission
     */
    public function store(ContactUsRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            // Save to local database
            $contactUs = ContactUs::create($validatedData);

            // Send to Odoo
            $odooResponse = $this->odooService->submitContactUs($validatedData);

            if (!$odooResponse['success']) {
                Log::warning('ContactUs: Failed to send to Odoo', [
                    'contact_us_id' => $contactUs->id,
                    'odoo_response' => $odooResponse
                ]);
            }

            return $this->successResponse(
                [
                    'id' => $contactUs->id,
                    'full_name' => $contactUs->full_name,
                    'phone' => $contactUs->phone,
                    'email' => $contactUs->email,
                    'message' => $contactUs->message,
                    'created_at' => $contactUs->created_at,
                    'odoo_success' => $odooResponse['success'] ?? false,
                ],
                'Contact form submitted successfully',
                201
            );

        } catch (\Exception $e) {
            Log::error('ContactUsController: Error submitting contact form', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse(
                'Failed to submit contact form: ' . $e->getMessage(),
                500
            );
        }
    }
}
