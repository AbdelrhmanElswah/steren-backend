<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CarListingCreateRequest;
use App\Http\Requests\Api\CarListingCreateFormRequest;
use App\Http\Resources\Api\CarListingResource;
use App\Models\CarListing;
use App\Models\ListingPromotion;
use App\Models\Media;
use App\Models\Document;
use App\Models\CarInspection;
use App\Services\Odoo\CarListingService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use App\Traits\HandlesFileUpload;
use App\Traits\DocumentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarListingApiController extends Controller
{
    use ApiResponse, HandlesFileUpload, DocumentTrait;

    public function __construct(
        private CarListingService $carListingService,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Create a new car listing from UI data and sync with Odoo
     */
    public function store(CarListingCreateRequest $request)
    {
        try {
            // Check if user can create listing based on subscription or free tier
            $canCreate = $this->subscriptionService->canCreateListing($request->user());
            
            if (!$canCreate['can_create']) {
                return $this->errorResponse(
                    $canCreate['reason'],
                    403,
                    [
                        'code' => $canCreate['code'],
                        'current_listings' => $canCreate['current_listings'] ?? 0,
                        'max_listings' => $canCreate['max_listings'] ?? 0
                    ]
                );
            }

            DB::beginTransaction();

            // Get validated data from request
            $validatedData = $request->validated();

            // Add authenticated user_id to the data
            $validatedData['user_id'] = $request->user()->id;

            // Validate photo count if images are provided
            if ($request->has('images') && is_array($request->images)) {
                $photoCount = count($request->images);
                $canUpload = $this->subscriptionService->canUploadPhotos($request->user(), $photoCount);
                
                if (!$canUpload['can_upload']) {
                    return $this->errorResponse(
                        $canUpload['reason'],
                        403,
                        [
                            'code' => $canUpload['code'],
                            'max_photos' => $canUpload['max_photos'],
                            'attempted_photos' => $canUpload['attempted_photos'] ?? $photoCount
                        ]
                    );
                }
            }

            // Map nested data to flat database structure
            $carListingData = $this->mapToCarListingData($validatedData);

            // Handle promotion selection
            $promotionData = $this->handlePromotionSelection($validatedData, $request->user());
            $carListingData = array_merge($carListingData, $promotionData);

            // Create the car listing in local database
            $carListing = CarListing::create($carListingData);

            // Consume slot if user is free tier (no active subscription)
            // This must happen after listing creation to ensure slot is consumed
            $this->subscriptionService->consumeSlot($request->user());

            // Handle images if provided
            if ($request->has('images') && is_array($request->images)) {
                $this->handleImages($carListing, $request->images);
            }

            // Handle inspection data and file if provided
            if ($request->has('inspection') || $request->has('inspection_file')) {
                $this->handleInspectionData($carListing, $validatedData);
            }

            // Prepare data for Odoo API
            $odooData = $this->prepareOdooData($carListing, $validatedData);

            // Send to Odoo API
            $odooResponse = $this->sendToOdoo($odooData);

            if ($odooResponse) {
                $odooId = $this->extractOdooListingId($odooResponse);
                if ($odooId) {
                    $carListing->update(['odoo_id' => $odooId]);
                    Log::info('Saved Odoo ID to listing', [
                        'listing_id' => $carListing->id,
                        'odoo_id' => $odooId
                    ]);
                }
            }

            DB::commit();

            // Get subscription info for response
            $subscriptionInfo = $this->subscriptionService->canCreateListing($request->user());

            // Load all necessary relationships for comprehensive response
            $carListing->load([
                'brand', 'carModel', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder', 'user', 'mileageType',
                'features', 'specifications', 'listingPromotion'
            ]);

            // Prepare subscription info response
            $subscriptionResponse = [
                'remaining_listings' => $subscriptionInfo['remaining_listings'] ?? null,
                'max_listings' => $subscriptionInfo['max_listings'] ?? null,
                'current_listings' => $subscriptionInfo['current_listings'] ?? 0,
                'plan_name' => $subscriptionInfo['plan_name'] ?? 'Free'
            ];

            // Add slots info for free users
            if (isset($subscriptionInfo['slots'])) {
                $subscriptionResponse['slots'] = $subscriptionInfo['slots'];
            }

            return $this->createdResponse([
                'car_listing' => new CarListingResource($carListing),
                'status' => 'pending',
                'message' => 'Car listing created successfully and is pending approval from our team.',
                'subscription_info' => $subscriptionResponse,
                'odoo_response' => $odooResponse
            ], 'Car listing created successfully and is pending approval');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Car listing creation failed: ' . $e->getMessage());
            
            return $this->errorResponse(
                'Failed to create car listing: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Map nested request data to flat car listing database structure
     */
    private function mapToCarListingData(array $validatedData)
    {
        $carInfo = $validatedData['car_info'];
        
        // Get user and their partner_id
        $user = \App\Models\User::find($validatedData['user_id']);
        
        return [
            'user_id' => $validatedData['user_id'],
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'price' => $validatedData['price'],
            'price_currency' => $validatedData['price_currency'],
            'vin' => isset($carInfo['vin']) ? substr($carInfo['vin'], 0, 17) : null,
            'car_brand_id' => $carInfo['brand'],
            'car_model_id' => $carInfo['model'],
            'year' => $carInfo['year'],
            'condition_type' => $carInfo['condition'],
            'gcc_import' => $carInfo['gcc_import'] ?? null,
            'car_body_type_id' => $carInfo['body_type'],
            'engine_size' => $carInfo['engine_size'] ?? null,
            'fuel_type_id' => $this->getFuelTypeId($carInfo['fuel_type'] ?? null),
            'cylinder_id' => $this->getCylinderId($carInfo['cylinders'] ?? null),
            'transmission_id' => $this->getTransmissionId($carInfo['transmission'] ?? null),
            'seating_capacity' => $carInfo['seating_capacity'] ?? null,
            'exterior_color_id' => $this->getExteriorColorId($carInfo['exterior_colour'] ?? null),
            'has_warranty' => ($carInfo['warranty'] ?? 'no') === 'yes',
            'drive_type_id' => $this->getDriveTypeId($carInfo['drive_type'] ?? null),
            'mileage_type_id' => $this->getMileageTypeId($carInfo['mileage_type'] ?? 'KM'),
            'mileage' => $carInfo['mileage'],
            'plate_status' => $carInfo['plate_status'] ?? null,
            'status' => 'pending', // All new listings start as pending until approved by Odoo
            'views' => 0,
            'is_verified' => ($validatedData['seller_info']['type'] ?? '') === 'verified_seller',
            'origin_country' => $carInfo['gcc_import'] ?? 'UAE', 
            'location_id' => 1, 
        ];
    }

    /**
     * Get fuel type ID by name
     */
    private function getFuelTypeId($fuelTypeName)
    {
        if (!$fuelTypeName) return null;
        
        $fuelType = \App\Models\FuelType::where('name', $fuelTypeName)->first();
        return $fuelType ? $fuelType->id : null;
    }

    /**
     * Get cylinder ID by name
     */
    private function getCylinderId($cylinders)
    {
        if (!$cylinders) return null;
        
        $cylinder = \App\Models\CylinderOption::where('name', $cylinders)->first();
        return $cylinder ? $cylinder->id : null;
    }

    /**
     * Get transmission ID by name
     */
    private function getTransmissionId($transmissionName)
    {
        if (!$transmissionName) return null;
        
        $transmission = \App\Models\Transmission::where('name', $transmissionName)->first();
        return $transmission ? $transmission->id : null;
    }

    /**
     * Get exterior color ID by name
     */
    private function getExteriorColorId($colorName)
    {
        if (!$colorName) return null;
        
        $color = \App\Models\ExteriorColor::where('name', $colorName)->first();
        return $color ? $color->id : null;
    }

    /**
     * Get drive type ID by name
     */
    private function getDriveTypeId($driveTypeName)
    {
        if (!$driveTypeName) return null;
        
        $driveType = \App\Models\DriveType::where('name', $driveTypeName)->first();
        return $driveType ? $driveType->id : null;
    }

    /**
     * Get mileage type ID by name
     */
    private function getMileageTypeId($mileageTypeName)
    {
        if (!$mileageTypeName) return null;
        
        $mileageType = \App\Models\MileageType::where('name', $mileageTypeName)->first();
        return $mileageType ? $mileageType->id : null;
    }

    /**
     * Handle image uploads from base64 data
     */
    private function handleImages(CarListing $carListing, array $images)
    {
        foreach ($images as $index => $base64Image) {
            try {
                // Decode base64 image
                $imageData = base64_decode($base64Image);
                
                if ($imageData === false) {
                    continue; // Skip invalid base64 data
                }

                // Generate filename
                $filename = 'car_listing_' . $carListing->id . '_' . ($index + 1) . '_' . time() . '.jpg';
                $path = 'car_listings/' . $carListing->id . '/' . $filename;

                // Store image to AWS S3
                Storage::disk('s3')->put($path, $imageData);
                
                // Get the full S3 URL
                $url = Storage::disk('s3')->url($path);

                // Create media record
                Media::create([
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'file_path' => $path,
                    'url' => $url,
                    'file_type' => 'image',
                    'file_size' => strlen($imageData),
                    'mime_type' => 'image/jpeg',
                    'is_primary' => $index === 0, // First image is primary
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to process image ' . ($index + 1) . ' for car listing ' . $carListing->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle inspection data and file upload
     */
    private function handleInspectionData(CarListing $carListing, array $validatedData)
    {
        try {
            $inspectionData = $validatedData['inspection'] ?? [];
            
            if (!empty($inspectionData)) {
                // Create CarInspection record with foreign key to car_listing
                $inspection = CarInspection::create([
                    'car_listing_id' => $carListing->id,
                    'inspection_number' => 'INS-' . $carListing->id . '-' . time(),
                    'inspection_date' => now(),
                    'status' => 'completed',
                    'right_side' => $inspectionData['right_side'] ?? false,
                    'left_side' => $inspectionData['left_side'] ?? false,
                    'roof' => $inspectionData['roof'] ?? false,
                    'front' => $inspectionData['front'] ?? false,
                    'back' => $inspectionData['back'] ?? false,
                ]);

                Log::info('Inspection record created for car listing ' . $carListing->id, [
                    'inspection_id' => $inspection->id
                ]);

                // Handle inspection file upload if provided
                if (!empty($validatedData['inspection_file']) && !empty($validatedData['inspection_file_name'])) {
                    $this->handleInspectionFileUpload($inspection, $validatedData);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle inspection data for car listing ' . $carListing->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle inspection file upload using DocumentTrait
     */
    private function handleInspectionFileUpload(CarInspection $inspection, array $validatedData)
    {
        try {
            $base64Data = $validatedData['inspection_file'];
            $filename = $validatedData['inspection_file_name'];

            // Decode base64 data
            $fileData = base64_decode($base64Data);
            
            if ($fileData === false) {
                Log::warning('Invalid base64 data for inspection file in inspection ' . $inspection->id);
                return;
            }

            // Create a temporary file to simulate UploadedFile
            $tempPath = tempnam(sys_get_temp_dir(), 'inspection_');
            file_put_contents($tempPath, $fileData);

            // Create UploadedFile instance
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $filename,
                'application/pdf',
                null,
                true // test mode
            );

            // Use DocumentTrait to upload the document to CarInspection
            $document = $this->uploadDocument(
                $uploadedFile,
                CarInspection::class,
                $inspection->id,
                'inspection_report',
                'Vehicle inspection report'
            );

            if ($document) {
                Log::info('Inspection file uploaded successfully for inspection ' . $inspection->id, [
                    'document_id' => $document->id,
                    'filename' => $filename
                ]);
            } else {
                Log::warning('Failed to upload inspection file for inspection ' . $inspection->id);
            }

            // Clean up temp file
            unlink($tempPath);

        } catch (\Exception $e) {
            Log::error('Failed to upload inspection file for inspection ' . $inspection->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Prepare data for Odoo API in the required format
     */
    private function prepareOdooData(CarListing $carListing, array $validatedData)
    {
        $carInfo = $validatedData['car_info'];
        $sellerInfo = $validatedData['seller_info'];

        // Get partner_id from user
        $user = \App\Models\User::find($validatedData['user_id']);

        return [
            'partner_id' => $user->partner_id,
            'car_info' => [
                'vin' => isset($carInfo['vin']) ? substr($carInfo['vin'], 0, 17) : '',
                'brand' => $carInfo['brand'],
                'model' => $carInfo['model'],
                'condition' => $carInfo['condition'],
                'year' => $carInfo['year'],
                'gcc_import' => $carInfo['gcc_import'] ?? '',
                'body_type' => $carInfo['body_type'],
                'engine_size' => $carInfo['engine_size'] ?? 0,
                'fuel_type' => $carInfo['fuel_type'] ?? '',
                'cylinders' => $carInfo['cylinders'] ?? 0,
                'transmission' => $carInfo['transmission'] ?? '',
                'seating_capacity' => $carInfo['seating_capacity'] ?? 0,
                'exterior_colour' => $carInfo['exterior_colour'] ?? '',
                'warranty' => $carInfo['warranty'] ?? 'no',
                'drive_type' => $carInfo['drive_type'] ?? '',
                'mileage_type' => $carInfo['mileage_type'] ?? 'KM',
                'mileage' => $carInfo['mileage'],
                'plate_status' => $carInfo['plate_status'] ?? '',
            ],
            'price_currency' => $validatedData['price_currency'],
            'price' => $validatedData['price'],
            'seller_info' => [
                'type' => $sellerInfo['type'],
            ],
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? '',
            'images' => $validatedData['images'] ?? [],
            'inspection' => $validatedData['inspection'] ?? null,
            'inspection_file' => $validatedData['inspection_file'] ?? null,
            'inspection_file_name' => $validatedData['inspection_file_name'] ?? null,
        ];
    }

    /**
     * Prepare Odoo data from form data
     */
    private function prepareOdooDataFromForm(CarListing $carListing, array $validatedData, $request)
    {
        // Get partner_id from user
        $user = \App\Models\User::find($validatedData['user_id']);

        // Get related model names for Odoo
        $fuelTypeName = $this->getFuelTypeName($validatedData['fuel_type_id'] ?? null);
        $transmissionName = $this->getTransmissionName($validatedData['transmission_id'] ?? null);
        $driveTypeName = $this->getDriveTypeName($validatedData['drive_type_id'] ?? null);
        $mileageTypeName = $this->getMileageTypeName($validatedData['mileage_type_id'] ?? null);
        $exteriorColorName = $this->getExteriorColorName($validatedData['exterior_color_id'] ?? null);
        $cylinderName = $this->getCylinderName($validatedData['cylinder_id'] ?? null);

        // Get city_id from location
        $cityId = null;
        if (isset($validatedData['location_id'])) {
            $location = \App\Models\Location::find($validatedData['location_id']);
            $cityId = $location ? $location->city_id : null;
        }

        // Prepare inspection data
        $inspectionData = [
            'right_side' => $validatedData['inspection_right_side'] ?? false,
            'left_side' => $validatedData['inspection_left_side'] ?? false,
            'roof' => $validatedData['inspection_roof'] ?? false,
            'front' => $validatedData['inspection_front'] ?? false,
            'back' => $validatedData['inspection_back'] ?? false,
        ];

        // Get inspection file name if provided
        $inspectionFileName = null;
        if (isset($validatedData['inspection_file_name'])) {
            $inspectionFileName = $validatedData['inspection_file_name'];
        } elseif ($request->hasFile('inspection_file')) {
            $inspectionFileName = $request->file('inspection_file')->getClientOriginalName();
        }

        // Get brand and model data for Odoo
        $brand = \App\Models\CarBrand::find($validatedData['car_brand_id']);
        $model = \App\Models\CarModel::find($validatedData['car_model_id']);
        $bodyType = \App\Models\BodyType::find($validatedData['car_body_type_id']);

        // Validate that Odoo IDs exist (check for null, empty string, but allow 0)
        $missingOdooIds = [];
        if (!$brand || is_null($brand->odoo_id) || $brand->odoo_id === '') {
            $missingOdooIds[] = "Brand (ID: {$validatedData['car_brand_id']})";
        }
        if (!$model || is_null($model->odoo_id) || $model->odoo_id === '') {
            $missingOdooIds[] = "Model (ID: {$validatedData['car_model_id']})";
        }
        if (!$bodyType || is_null($bodyType->odoo_id) || $bodyType->odoo_id === '') {
            $missingOdooIds[] = "Body Type (ID: {$validatedData['car_body_type_id']})";
        }

        if (!empty($missingOdooIds)) {
            Log::error('Missing Odoo IDs for car listing creation', [
                'missing_odoo_ids' => $missingOdooIds,
                'brand' => $brand ? [
                    'id' => $brand->id, 
                    'name' => $brand->name, 
                    'odoo_id' => $brand->odoo_id,
                    'odoo_id_type' => gettype($brand->odoo_id),
                    'odoo_id_is_null' => is_null($brand->odoo_id),
                    'odoo_id_is_empty' => $brand->odoo_id === ''
                ] : null,
                'model' => $model ? [
                    'id' => $model->id, 
                    'name' => $model->name, 
                    'odoo_id' => $model->odoo_id,
                    'odoo_id_type' => gettype($model->odoo_id),
                    'odoo_id_is_null' => is_null($model->odoo_id),
                    'odoo_id_is_empty' => $model->odoo_id === ''
                ] : null,
                'body_type' => $bodyType ? [
                    'id' => $bodyType->id, 
                    'name' => $bodyType->name, 
                    'odoo_id' => $bodyType->odoo_id,
                    'odoo_id_type' => gettype($bodyType->odoo_id),
                    'odoo_id_is_null' => is_null($bodyType->odoo_id),
                    'odoo_id_is_empty' => $bodyType->odoo_id === ''
                ] : null
            ]);
            
            // Return error response instead of sending to Odoo
            return [
                'error' => true,
                'message' => 'Missing Odoo IDs for required data',
                'missing_odoo_ids' => $missingOdooIds,
                'details' => 'Please ensure all brands, models, and body types have Odoo IDs before creating listings'
            ];
        }

        // Log the data being sent to Odoo for debugging
        Log::info('Preparing Odoo data', [
            'brand' => [
                'laravel_id' => $validatedData['car_brand_id'],
                'odoo_id' => $brand->odoo_id,
                'name' => $brand->name
            ],
            'model' => [
                'laravel_id' => $validatedData['car_model_id'],
                'odoo_id' => $model->odoo_id,
                'name' => $model->name
            ],
            'body_type' => [
                'laravel_id' => $validatedData['car_body_type_id'],
                'odoo_id' => $bodyType->odoo_id,
                'name' => $bodyType->name
            ]
        ]);

        // Prepare car_info with all required fields - Use Odoo IDs only
        $carInfo = [
            'vin' => $validatedData['vin'] ?? '',
            'brand' => $brand->odoo_id, // Use Odoo ID only
            'model' => $model->odoo_id, // Use Odoo ID only
            'condition' => $validatedData['condition_type'],
            'year' => $validatedData['year'],
            'gcc_import' => $validatedData['gcc_import'] ?? '',
            'body_type' => $bodyType->odoo_id, // Use Odoo ID only
            'engine_size' => $validatedData['engine_size'] ?? 0,
            'fuel_type' => $fuelTypeName,
            'cylinders' => $cylinderName,
            'transmission' => $transmissionName,
            'seating_capacity' => $validatedData['seating_capacity'] ?? 0,
            'exterior_colour' => $exteriorColorName,
            'warranty' => ($validatedData['has_warranty'] ?? false) ? 'yes' : 'no',
            'drive_type' => $driveTypeName,
            'mileage_type' => $mileageTypeName,
            'mileage' => $validatedData['mileage'],
            'plate_status' => $validatedData['plate_status'] ?? '',
            'inspection' => $inspectionData,
        ];

        // Add inspection file name if available
        if ($inspectionFileName) {
            $carInfo['inspection_file_name'] = $inspectionFileName;
        }

        return [
            'partner_id' => $user->partner_id,
            'city_id' => $cityId,
            'car_info' => $carInfo,
            'price_currency' => $validatedData['price_currency'],
            'price' => $validatedData['price'],
            'seller_info' => [
                'type' => $validatedData['seller_type'] ?? 'private_seller',
            ],
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? '',
            'images' => $validatedData['images'] ?? [],
        ];
    }

    /**
     * Send data to Odoo API
     */
    private function sendToOdoo(array $data)
    {
        try {
            // Get Odoo API URL from config
            $odooBaseUrl = config('services.odoo.base_url', 'http://localhost:8069');
            $odooApiUrl = $odooBaseUrl . '/api/listing/create';
            
            Log::info('Sending listing to Odoo', [
                'url' => $odooApiUrl,
                'payload' => $data
            ]);
            
            // Make HTTP request to Odoo
            $response = \Http::timeout(30)->post($odooApiUrl, $data);

            Log::info('Odoo listing creation response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                $errorBody = $response->json();
                Log::error('Odoo API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error_details' => $errorBody,
                    'data' => $data
                ]);
                
                // Return error response for better debugging
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $errorBody['result']['error'] ?? 'Odoo API request failed',
                    'details' => $errorBody['result']['details'] ?? null,
                    'odoo_response' => $errorBody
                ];
            }

        } catch (\Exception $e) {
            Log::error('Odoo API request exception: ' . $e->getMessage(), [
                'data' => $data
            ]);
            return null;
        }
    }

    private function extractOdooListingId(array $response): ?string
    {
        $possiblePaths = [
            'result.data.listing_id',
            'data.listing_id',
            'listing_id',
            'result.listing_id',
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
        
        Log::warning('Could not extract Odoo listing ID from response', [
            'response' => $response
        ]);
        
        return null;
    }

    /**
     * Handle promotion selection for car listing
     */
    private function handlePromotionSelection(array $validatedData, $user)
    {
        $promotionData = [
            'listing_promotion_id' => null,
            'promotion_price_paid' => null,
            'promotion_currency' => 'IQD',
            'promotion_expires_at' => null,
            'is_promoted' => false,
            'promotion_started_at' => null,
        ];

        // Check if promotion is selected in the request
        $selectedPromotionType = $validatedData['promotion_type'] ?? 'free';
        
        // Get the promotion based on type
        $promotion = ListingPromotion::where('type', $selectedPromotionType)
            ->where('is_active', true)
            ->first();

        if (!$promotion) {
            // Fallback to default promotion if requested type not found
            $promotion = ListingPromotion::getDefault();
        }

        if ($promotion) {
            $promotionData['listing_promotion_id'] = $promotion->id;
            $promotionData['promotion_currency'] = $promotion->currency;
            $promotionData['is_promoted'] = $promotion->isSponsored();
            $promotionData['promotion_started_at'] = now();

            // Set expiration date based on promotion duration
            $promotionData['promotion_expires_at'] = $promotion->calculateExpirationDate();

            // If it's a sponsored promotion, record the price paid
            if ($promotion->isSponsored() && $promotion->price > 0) {
                $promotionData['promotion_price_paid'] = $promotion->price;
                
                // Log promotion purchase for analytics
                Log::info('Sponsored listing promotion selected', [
                    'user_id' => $user->id,
                    'promotion_id' => $promotion->id,
                    'promotion_name' => $promotion->name,
                    'price_paid' => $promotion->price,
                    'currency' => $promotion->currency,
                    'expires_at' => $promotionData['promotion_expires_at'],
                ]);
            }
        }

        return $promotionData;
    }

    /**
     * Create a new car listing from form-data (multipart/form-data)
     */
    public function storeFormData(CarListingCreateFormRequest $request)
    {
        try {
            // Check if user can create listing based on subscription or free tier
            $canCreate = $this->subscriptionService->canCreateListing($request->user());
            
            if (!$canCreate['can_create']) {
                return $this->errorResponse(
                    $canCreate['reason'],
                    403,
                    [
                        'code' => $canCreate['code'],
                        'current_listings' => $canCreate['current_listings'] ?? 0,
                        'max_listings' => $canCreate['max_listings'] ?? 0
                    ]
                );
            }

            DB::beginTransaction();

            // Get validated data from request
            $validatedData = $request->validated();

            // Add authenticated user_id to the data
            $validatedData['user_id'] = $request->user()->id;

            // Validate photo count if images are provided
            if ($request->hasFile('images')) {
                $photoCount = count($request->file('images'));
                $canUpload = $this->subscriptionService->canUploadPhotos($request->user(), $photoCount);
                
                if (!$canUpload['can_upload']) {
                    return $this->errorResponse(
                        $canUpload['reason'],
                        403,
                        [
                            'code' => $canUpload['code'],
                            'max_photos' => $canUpload['max_photos'],
                            'attempted_photos' => $canUpload['attempted_photos'] ?? $photoCount
                        ]
                    );
                }
            }

            // Map form data to car listing structure
            $carListingData = $this->mapFormDataToCarListingData($validatedData);

            // Handle promotion selection (if any)
            $promotionData = $this->handlePromotionSelection($validatedData, $request->user());
            $carListingData = array_merge($carListingData, $promotionData);

            // Create the car listing in local database
            $carListing = CarListing::create($carListingData);

            // Consume slot if user is free tier (no active subscription)
            $this->subscriptionService->consumeSlot($request->user());

            // Handle image uploads if provided
            if ($request->hasFile('images')) {
                $this->handleImageUploads($carListing, $request->file('images'));
            }

            // Handle inspection data and file if provided
            if ($this->hasInspectionData($validatedData) || $request->hasFile('inspection_file')) {
                $this->handleInspectionFormData($carListing, $validatedData, $request);
            }

            // Prepare data for Odoo API
            $odooData = $this->prepareOdooDataFromForm($carListing, $validatedData, $request);

            // Check if Odoo data preparation failed
            if (isset($odooData['error']) && $odooData['error']) {
                DB::rollBack();
                return $this->errorResponse(
                    $odooData['message'],
                    422,
                    [
                        'missing_odoo_ids' => $odooData['missing_odoo_ids'] ?? [],
                        'details' => $odooData['details'] ?? null
                    ]
                );
            }

            // Send to Odoo API
            $odooResponse = $this->sendToOdoo($odooData);

            if ($odooResponse) {
                $odooId = $this->extractOdooListingId($odooResponse);
                if ($odooId) {
                    $carListing->update(['odoo_id' => $odooId]);
                    Log::info('Saved Odoo ID to listing', [
                        'listing_id' => $carListing->id,
                        'odoo_id' => $odooId
                    ]);
                }
            }

            DB::commit();

            // Get subscription info for response
            $subscriptionInfo = $this->subscriptionService->canCreateListing($request->user());

            // Load all necessary relationships for comprehensive response
            $carListing->load([
                'brand', 'carModel', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder', 'user', 'mileageType',
                'features', 'specifications', 'listingPromotion'
            ]);

            // Prepare subscription info response
            $subscriptionResponse = [
                'remaining_listings' => $subscriptionInfo['remaining_listings'] ?? null,
                'max_listings' => $subscriptionInfo['max_listings'] ?? null,
                'current_listings' => $subscriptionInfo['current_listings'] ?? 0,
                'plan_name' => $subscriptionInfo['plan_name'] ?? 'Free'
            ];

            // Add slots info for free users
            if (isset($subscriptionInfo['slots'])) {
                $subscriptionResponse['slots'] = $subscriptionInfo['slots'];
            }

            return $this->createdResponse([
                'car_listing' => new CarListingResource($carListing),
                'status' => 'pending',
                'message' => 'Car listing created successfully and is pending approval from our team.',
                'subscription_info' => $subscriptionResponse,
                'odoo_response' => $odooResponse
            ], 'Car listing created successfully and is pending approval');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Car listing creation failed: ' . $e->getMessage());
            
            return $this->errorResponse(
                'Failed to create car listing: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Map form data to car listing database structure (Flutter compatible)
     */
    private function mapFormDataToCarListingData(array $data): array
    {
        return [
            'vin' => $data['vin'] ?? null,
            'car_brand_id' => $data['car_brand_id'],
            'car_model_id' => $data['car_model_id'],
            'condition_type' => $data['condition_type'],
            'year' => $data['year'],
            'gcc_import' => $data['gcc_import'] ?? null,
            'car_body_type_id' => $data['car_body_type_id'],
            'engine_size' => $data['engine_size'] ?? null,
            'fuel_type_id' => $data['fuel_type_id'] ?? null,
            'cylinder_id' => $data['cylinder_id'] ?? null,
            'transmission_id' => $data['transmission_id'] ?? null,
            'seating_capacity' => $data['seating_capacity'] ?? null,
            'exterior_color_id' => $data['exterior_color_id'] ?? null,
            'has_warranty' => $data['has_warranty'] ?? false,
            'drive_type_id' => $data['drive_type_id'] ?? null,
            'mileage_type_id' => $data['mileage_type_id'] ?? null,
            'mileage' => $data['mileage'],
            'plate_status' => $data['plate_status'] ?? null,
            'doors' => $data['doors'] ?? null,
            'trim' => $data['trim'] ?? null,
            'origin_country' => $data['origin_country'] ?? null,
            'is_inspected' => $data['is_inspected'] ?? false,
            'is_verified' => $data['is_verified'] ?? false,
            'has_insurance' => $data['has_insurance'] ?? false,
            'location_id' => $data['city_id'] ?? null,
            'import_type_id' => $data['import_type_id'] ?? null,
            'warranty_status_id' => $data['warranty_status_id'] ?? null,
            'engine_type' => $data['engine_type'] ?? null,
            'horsepower' => $data['horsepower'] ?? null,
            'torque' => $data['torque'] ?? null,
            'acceleration_0_100' => $data['acceleration_0_100'] ?? null,
            'top_speed' => $data['top_speed'] ?? null,
            'mileage_efficiency' => $data['mileage_efficiency'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'status_id' => $data['status_id'] ?? null,
            'operator_decision' => $data['operator_decision'] ?? 'pending',
            'is_featured' => $data['is_featured'] ?? false,
            'is_new_arrival' => $data['is_new_arrival'] ?? false,
            'price' => $data['price'],
            'price_currency' => $data['price_currency'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'user_id' => $data['user_id'],
        ];
    }

    /**
     * Handle image uploads from form data to AWS S3 using HandlesFileUpload trait
     */
    private function handleImageUploads(CarListing $carListing, array $images): void
    {
        foreach ($images as $index => $image) {
            try {
                // Use HandlesFileUpload trait for S3 upload
                $uploadResult = $this->uploadFileToS3($image, $carListing, 'car-listings');
                
                // Create media record with proper structure
                Media::create([
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'media' => $uploadResult['file_name'],
                    'path_file_name' => $uploadResult['path'],
                    'file_type' => $uploadResult['file_type'],
                    'order' => $index,
                    'info' => $index === 0 ? 'primary' : 'secondary',
                ]);
                
                Log::info('Image uploaded successfully for car listing', [
                    'car_listing_id' => $carListing->id,
                    'file_name' => $uploadResult['file_name'],
                    'url' => $uploadResult['url']
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to upload image for car listing', [
                    'car_listing_id' => $carListing->id,
                    'error' => $e->getMessage()
                ]);
                // Continue with other images even if one fails
            }
        }
    }

    /**
     * Handle inspection data from form data
     */
    private function handleInspectionFormData(CarListing $carListing, array $data, $request): void
    {
        $inspectionData = [
            'car_listing_id' => $carListing->id,
            'right_side' => $data['inspection_right_side'] ?? false,
            'left_side' => $data['inspection_left_side'] ?? false,
            'roof' => $data['inspection_roof'] ?? false,
            'front' => $data['inspection_front'] ?? false,
            'back' => $data['inspection_back'] ?? false,
            'inspection_number' => 'INSP-' . $carListing->id . '-' . time(),
            'inspection_date' => now()->format('Y-m-d'),
        ];

        // Handle inspection file upload if provided
        if ($request->hasFile('inspection_file')) {
            $inspectionFile = $this->handleInspectionFileUploadFromForm($request->file('inspection_file'));
            if ($inspectionFile) {
                $inspectionData = array_merge($inspectionData, $inspectionFile);
            }
        }

        CarInspection::create($inspectionData);
    }

    /**
     * Handle inspection file upload from form data to AWS S3
     */
    private function handleInspectionFileUploadFromForm($file): ?array
    {
        try {
            // Create a temporary CarListing object for the trait method
            $tempCarListing = new CarListing();
            $tempCarListing->id = 0; // Temporary ID for trait method
            
            // Use HandlesFileUpload trait for S3 upload
            $uploadResult = $this->uploadFileToS3($file, $tempCarListing, 'car-inspections');
            
            $document = Document::create([
                'file_path' => $uploadResult['path'],
                'url' => $uploadResult['url'],
                'file_name' => $uploadResult['file_name'],
                'file_type' => $uploadResult['file_type'],
                'file_size' => $file->getSize(),
                'document_type' => 'inspection_report',
            ]);

            Log::info('Inspection file uploaded successfully', [
                'file_name' => $uploadResult['file_name'],
                'url' => $uploadResult['url'],
                'document_id' => $document->id
            ]);

            return [
                'inspection_document_id' => $document->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to upload inspection file to S3: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if inspection data is provided
     */
    private function hasInspectionData(array $data): bool
    {
        return isset($data['inspection_right_side']) ||
               isset($data['inspection_left_side']) ||
               isset($data['inspection_roof']) ||
               isset($data['inspection_front']) ||
               isset($data['inspection_back']);
    }

    /**
     * Get fuel type name from ID
     */
    private function getFuelTypeName($fuelTypeId): string
    {
        if (!$fuelTypeId) return '';
        
        $fuelType = \App\Models\FuelType::find($fuelTypeId);
        return $fuelType ? strtolower($fuelType->name) : '';
    }

    /**
     * Get transmission name from ID
     */
    private function getTransmissionName($transmissionId): string
    {
        if (!$transmissionId) return '';
        
        $transmission = \App\Models\Transmission::find($transmissionId);
        return $transmission ? strtolower($transmission->name) : '';
    }

    /**
     * Get drive type name from ID
     */
    private function getDriveTypeName($driveTypeId): string
    {
        if (!$driveTypeId) return '';
        
        $driveType = \App\Models\DriveType::find($driveTypeId);
        return $driveType ? strtolower($driveType->name) : '';
    }

    /**
     * Get mileage type name from ID
     */
    private function getMileageTypeName($mileageTypeId): string
    {
        if (!$mileageTypeId) return 'km';
        
        $mileageType = \App\Models\MileageType::find($mileageTypeId);
        return $mileageType ? strtolower($mileageType->name) : 'km';
    }

    /**
     * Get exterior color name from ID
     */
    private function getExteriorColorName($exteriorColorId): string
    {
        if (!$exteriorColorId) return '';
        
        $exteriorColor = \App\Models\ExteriorColor::find($exteriorColorId);
        return $exteriorColor ? strtolower($exteriorColor->name) : '';
    }

    /**
     * Get cylinder value from ID
     */
    private function getCylinderName($cylinderId): string
    {
        if (!$cylinderId) return '';
        
        $cylinder = \App\Models\CylinderOption::find($cylinderId);
        return $cylinder ? (string) $cylinder->id : '';
    }
}