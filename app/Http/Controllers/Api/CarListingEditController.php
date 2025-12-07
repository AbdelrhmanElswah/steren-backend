<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CarListingEditRequest;
use App\Http\Requests\Api\CarListingEditFormRequest;
use App\Services\CarListingEditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CarListingEditController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CarListingEditService $carListingEditService
    ) {}

    /**
     * Create a new car listing edit
     */
    public function store(CarListingEditRequest $request)
    {
        try {
            // Create the edit record (JSON request)
            $validatedData = $request->validated();
            $carListingEdit = $this->carListingEditService->create($validatedData, false);
            
            // Get the original car listing
            $carListing = $carListingEdit->carListing;
            
            // Handle mixed images: existing URLs + new base64 images
            $existingImageUrls = $request->input('existing_image_urls', []);
            $newImageBase64 = $request->input('new_images', []);
            
            // Legacy support: if 'new_images' not provided, use 'images' field
            if (empty($newImageBase64) && isset($validatedData['images'])) {
                $newImageBase64 = $validatedData['images'];
            }
            
            // Process mixed images if we have existing URLs or new base64 images
            if (!empty($existingImageUrls) || !empty($newImageBase64)) {
                $this->carListingEditService->handleMixedImages(
                    $carListingEdit,
                    $carListing,
                    $existingImageUrls,
                    null, // No binary files for JSON
                    $newImageBase64
                );
            }
            
            // Prepare Odoo payload with only changed fields
            $odooPayload = $this->carListingEditService->prepareOdooEditPayload($carListingEdit, $carListing);
            
            // Send to Odoo API
            $odooResponse = $this->sendToOdoo($odooPayload);
            
            if ($odooResponse && !isset($odooResponse['error'])) {
                $odooId = $this->extractOdooEditId($odooResponse);
                if ($odooId) {
                    $carListingEdit->update(['odoo_id' => $odooId]);
                    
                    Log::info('Car listing edit synced to Odoo', [
                        'edit_id' => $carListingEdit->id,
                        'odoo_id' => $odooId
                    ]);
                }
            }
            
            return $this->createdResponse([
                'car_listing_edit' => $carListingEdit,
                'status' => 'pending',
                'message' => 'Car listing edit created successfully and is pending approval',
                'odoo_response' => $odooResponse
            ], 'Car listing edit created successfully');
            
        } catch (\Exception $e) {
            Log::error('Failed to create car listing edit: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to create car listing edit: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Send edit data to Odoo API
     */
    private function sendToOdoo(array $data)
    {
        try {
            $odooBaseUrl = config('services.odoo.base_url', 'http://localhost:8069');
            $odooApiUrl = $odooBaseUrl . '/api/listing/edit';
            
            Log::info('Sending car listing edit to Odoo', [
                'url' => $odooApiUrl,
                'payload' => $data
            ]);
            
            $response = Http::timeout(30)->post($odooApiUrl, $data);
            
            Log::info('Odoo edit response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);
            
            if ($response->successful()) {
                return $response->json();
            } else {
                $errorBody = $response->json();
                Log::error('Odoo edit API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error_details' => $errorBody
                ]);
                
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $errorBody['result']['error'] ?? 'Odoo API request failed',
                    'details' => $errorBody['result']['details'] ?? null,
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Odoo edit API request exception: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract Odoo edit ID from response
     */
    private function extractOdooEditId(array $response): ?string
    {
        $possiblePaths = [
            'result.data.edit_id',
            'data.edit_id',
            'edit_id',
            'result.edit_id',
            'id',
            'result.data.id',
            'data.id'
        ];
        
        foreach ($possiblePaths as $path) {
            $value = data_get($response, $path);
            if ($value !== null && (is_numeric($value) || is_string($value))) {
                return (string) $value;
            }
        }
        
        Log::warning('Could not extract Odoo edit ID from response', [
            'response' => $response
        ]);
        
        return null;
    }
    
    /**
     * Create a new car listing edit from form-data (multipart/form-data)
     */
    public function storeFormData(CarListingEditFormRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Create the edit record (form-data request)
            $validatedData = $request->validated();
            $carListingEdit = $this->carListingEditService->create($validatedData, true);
            
            // Get the original car listing
            $carListing = $carListingEdit->carListing;
            
            // Handle mixed images: existing URLs + new binary files
            $existingImageUrls = $request->input('existing_image_urls', []);
            $newImageFiles = null;
            
            if ($request->hasFile('new_images')) {
                $newImageFiles = $request->file('new_images');
                if (!is_array($newImageFiles)) {
                    $newImageFiles = [$newImageFiles];
                }
            } elseif ($request->hasFile('images')) {
                // Legacy support: if 'new_images' not provided, use 'images' field
                $newImageFiles = $request->file('images');
                if (!is_array($newImageFiles)) {
                    $newImageFiles = [$newImageFiles];
                }
            }
            
            // Process mixed images if we have existing URLs or new files
            if (!empty($existingImageUrls) || !empty($newImageFiles)) {
                $this->carListingEditService->handleMixedImages(
                    $carListingEdit,
                    $carListing,
                    $existingImageUrls,
                    $newImageFiles,
                    null // No base64 for form-data
                );
            }
            
            // Prepare Odoo payload with only changed fields
            $odooPayload = $this->carListingEditService->prepareOdooEditPayload($carListingEdit, $carListing);
            
            // Send to Odoo API
            $odooResponse = $this->sendToOdoo($odooPayload);
            
            if ($odooResponse && !isset($odooResponse['error'])) {
                $odooId = $this->extractOdooEditId($odooResponse);
                if ($odooId) {
                    $carListingEdit->update(['odoo_id' => $odooId]);
                    
                    Log::info('Car listing edit synced to Odoo', [
                        'edit_id' => $carListingEdit->id,
                        'odoo_id' => $odooId
                    ]);
                }
            }
            
            DB::commit();
            
            return $this->createdResponse([
                'car_listing_edit' => $carListingEdit,
                'status' => 'pending',
                'message' => 'Car listing edit created successfully and is pending approval',
                'odoo_response' => $odooResponse
            ], 'Car listing edit created successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create car listing edit: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to create car listing edit: ' . $e->getMessage(),
                500
            );
        }
    }
}
