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
use App\Services\UnifiedNotificationService;
use App\Traits\ApiResponse;
use App\Traits\HandlesFileUpload;
use App\Traits\DocumentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarListingApiController extends Controller
{
    use ApiResponse, HandlesFileUpload, DocumentTrait;

    public function __construct(
        private CarListingService $carListingService,
        private SubscriptionService $subscriptionService,
        private UnifiedNotificationService $notificationService
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
            $uploadedImageUrls = [];
            if ($request->has('images') && is_array($request->images)) {
                $uploadedImageUrls = $this->handleImages($carListing, $request->images);
            }

            // Handle inspection data and file if provided
            if ($request->has('inspection') || $request->has('inspection_file')) {
                $this->handleInspectionData($carListing, $validatedData);
            }

            // Prepare data for Odoo API
            $odooData = $this->prepareOdooData($carListing, $validatedData, $uploadedImageUrls);

            // Send to Odoo API
            $odooResponse = $this->sendToOdoo($odooData);

            if ($odooResponse) {
                $odooId = $this->extractOdooListingId($odooResponse);
                if ($odooId) {
                    // Extract Odoo response data
                    $odooData = $this->extractOdooResponseData($odooResponse);
                    
                    $carListing->update([
                        'odoo_id' => $odooId,
                        'odoo_listing_id' => $odooData['listing_id'] ?? null,
                        'odoo_vehicle_id' => $odooData['vehicle_id'] ?? null,
                        'odoo_seller_id' => $odooData['seller_id'] ?? null,
                        'odoo_city_id' => $odooData['city_id'] ?? null,
                        'odoo_listing_state' => $odooData['listing_state'] ?? null,
                    ]);
                    
                    Log::info('Saved Odoo data to listing', [
                        'listing_id' => $carListing->id,
                        'odoo_id' => $odooId,
                        'odoo_data' => $odooData
                    ]);
                }
            }

            DB::commit();

            // Send notification to user about successful listing creation
            $this->sendListingCreatedNotification($carListing, $request->user());

            // Get subscription info for response
            $subscriptionInfo = $this->subscriptionService->canCreateListing($request->user());

            // Load all necessary relationships for comprehensive response
            $carListing->load([
                'brand', 'carModel', 'bodyType', 'city', 'studio',
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
        
        $normalizedGccImport = $this->normalizeGccImport($carInfo['gcc_import'] ?? null);

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
            'car_condition_id' => $this->getCarConditionIdFromConditionType($carInfo['condition'] ?? null),
            'gcc_import' => $normalizedGccImport,
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
            'damaged_areas' => $carInfo['damaged_areas'] ?? [],
            'status' => 'pending', // All new listings start as pending until approved by Odoo
            'views' => 0,
            'is_verified' => ($validatedData['seller_info']['type'] ?? '') === 'verified_seller',
            'origin_country' => $normalizedGccImport ?? 'UAE',
            'city_id' => $carInfo['city_id'] ?? null,
            'location_id' => $this->getOrCreateLocationId($validatedData), 
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
     * Get car condition ID from condition_type
     * Maps "used" -> CarCondition "Used", "new" -> CarCondition "New"
     */
    private function getCarConditionIdFromConditionType($conditionType): ?int
    {
        if (!$conditionType) {
            return null;
        }

        // Normalize condition_type to match CarCondition name
        $conditionName = ucfirst(strtolower($conditionType)); // "used" -> "Used", "new" -> "New"
        
        $carCondition = \App\Models\CarCondition::where('name', $conditionName)->first();
        
        return $carCondition ? $carCondition->id : null;
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
    private function handleImages(CarListing $carListing, array $images): array
    {
        $uploadedUrls = [];

        foreach ($images as $index => $base64Image) {
            try {
                $extension = 'jpg';
                $mimeType = 'image/jpeg';
                $base64Payload = $base64Image;

                if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $base64Image, $matches)) {
                    $mimeType = strtolower($matches[1]);
                    $base64Payload = $matches[2];

                    $extensionMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                        'image/svg+xml' => 'svg',
                    ];
                    $extension = $extensionMap[$mimeType] ?? 'jpg';
                }

                // Decode base64 image
                $imageData = base64_decode($base64Payload);
                
                if ($imageData === false) {
                    continue; // Skip invalid base64 data
                }

                // Generate filename
                $filename = 'car_listing_' . $carListing->id . '_' . ($index + 1) . '_' . time() . '.' . $extension;
                $path = 'car_listings/' . $carListing->id . '/' . $filename;

                // Store image to AWS S3
                Storage::disk('s3')->put($path, $imageData, 'public');
                
                // Get the full S3 URL
                $url = Storage::disk('s3')->url($path);

                // Create media record with proper structure
                $mediaData = [
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'media' => $filename,
                    'path_file_name' => $path,
                    'file_type' => $mimeType,
                    'info' => $index === 0 ? 'primary' : 'secondary',
                ];
                
                // Only add order if the column exists
                if (Schema::hasColumn('media', 'order')) {
                    $mediaData['order'] = $index;
                }
                
                $media = Media::create($mediaData);
                $uploadedUrls[] = $media->public_url ?? $url;

            } catch (\Exception $e) {
                Log::warning('Failed to process image ' . ($index + 1) . ' for car listing ' . $carListing->id . ': ' . $e->getMessage());
            }
        }

        return $uploadedUrls;
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
    private function prepareOdooData(CarListing $carListing, array $validatedData, array $imageUrls = [])
    {
        $carInfo = $validatedData['car_info'];
        $sellerInfo = $validatedData['seller_info'];

        // Get partner_id from user
        $user = \App\Models\User::find($validatedData['user_id']);

        $normalizedGccImport = $this->normalizeGccImport($carInfo['gcc_import'] ?? null);

        return [
            'partner_id' => $user->partner_id,
            'car_info' => [
                'vin' => isset($carInfo['vin']) ? substr($carInfo['vin'], 0, 17) : '',
                'brand' => $carInfo['brand'],
                'model' => $carInfo['model'],
                'condition' => $carInfo['condition'],
                'year' => $carInfo['year'],
                'gcc_import' => $normalizedGccImport ?? '',
                'body_type' => $carInfo['body_type'],
                'engine_size' => $carInfo['engine_size'] ?? 0,
                'fuel_type' => $this->mapFuelTypeForOdoo($carInfo['fuel_type'] ?? ''),
                'cylinders' => $carInfo['cylinders'] ?? 0,
                'transmission' => $carInfo['transmission'] ?? '',
                'seating_capacity' => $carInfo['seating_capacity'] ?? 0,
                'exterior_colour' => $carInfo['exterior_colour'] ?? '',
                'warranty' => $carInfo['warranty'] ?? 'no',
                'drive_type' => $carInfo['drive_type'] ?? '',
                'mileage_type' => $carInfo['mileage_type'] ?? 'KM',
                'mileage' => $carInfo['mileage'],
                'plate_status' => $carInfo['plate_status'] ?? '',
                'trim' => $carInfo['trim'] ?? '',
                'damaged_areas' => $carInfo['damaged_areas'] ?? [],
            ],
            'price_currency' => $validatedData['price_currency'],
            'price' => $validatedData['price'],
            'seller_info' => [
                'type' => $sellerInfo['type'],
            ],
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? '',
            'images' => $imageUrls,
            'inspection' => $validatedData['inspection'] ?? null,
            'inspection_file' => $validatedData['inspection_file'] ?? null,
            'inspection_file_name' => $validatedData['inspection_file_name'] ?? null,
        ];
    }

    /**
     * Prepare Odoo data from form data
     */
    private function prepareOdooDataFromForm(?CarListing $carListing, array $validatedData, $request, array $imageUrls = [])
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

        // Resolve city information and corresponding Odoo ID
        $cityId = $validatedData['city_id'] ?? null;
        $city = null;
        $location = null;
        $cityOdooId = null;

        if (isset($validatedData['location_id'])) {
            $location = \App\Models\Location::find($validatedData['location_id']);
        }

        if ($cityId) {
            $city = \App\Models\City::find($cityId);
        } elseif ($location && !empty($location->city_id)) {
            $cityId = $location->city_id;
            $city = \App\Models\City::find($cityId);
        }

        if ($city && !empty($city->odoo_id)) {
            $cityOdooId = $city->odoo_id;
        }

        if (!$cityOdooId && $location && !empty($location->odoo_id)) {
            $cityOdooId = $location->odoo_id;
        }

        if ($city && empty($cityOdooId)) {
            Log::warning('City missing Odoo ID for car listing creation', [
                'city_id' => $city->id,
                'city_name' => $city->name,
            ]);
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
        } elseif ($brand->odoo_id == 0 || $brand->odoo_id == $brand->id) {
            // Warn if odoo_id is 0 or matches Laravel ID (suspicious)
            Log::warning('Brand odoo_id might be incorrect', [
                'brand_id' => $brand->id,
                'odoo_id' => $brand->odoo_id,
                'name' => $brand->name
            ]);
        }
        
        if (!$model || is_null($model->odoo_id) || $model->odoo_id === '') {
            $missingOdooIds[] = "Model (ID: {$validatedData['car_model_id']})";
        } elseif ($model->odoo_id == 0 || $model->odoo_id == $model->id) {
            // Warn if odoo_id is 0 or matches Laravel ID (suspicious)
            Log::warning('Model odoo_id might be incorrect', [
                'model_id' => $model->id,
                'odoo_id' => $model->odoo_id,
                'name' => $model->name
            ]);
        }
        
        if (!$bodyType || is_null($bodyType->odoo_id) || $bodyType->odoo_id === '') {
            $missingOdooIds[] = "Body Type (ID: {$validatedData['car_body_type_id']})";
        } elseif ($bodyType->odoo_id == 0 || $bodyType->odoo_id == $bodyType->id) {
            // Warn if odoo_id is 0 or matches Laravel ID (suspicious)
            Log::warning('Body Type odoo_id might be incorrect - odoo_id matches Laravel ID', [
                'body_type_id' => $bodyType->id,
                'odoo_id' => $bodyType->odoo_id,
                'name' => $bodyType->name,
                'warning' => 'odoo_id matches Laravel ID, this might not be a valid Odoo ID'
            ]);
            // Don't fail validation here, but log it for debugging
        }

        if ((($cityId ?? null) || isset($validatedData['location_id'])) && empty($cityOdooId)) {
            $missingOdooIds[] = $cityId
                ? "City (ID: {$cityId})"
                : "Location (ID: {$validatedData['location_id']})";
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
                ] : null,
                'city' => [
                    'provided_city_id' => $validatedData['city_id'] ?? null,
                    'resolved_city_id' => $city?->id,
                    'city_name' => $city?->name,
                    'city_odoo_id' => $city?->odoo_id,
                    'location_id' => $validatedData['location_id'] ?? null,
                    'location_odoo_id' => $location?->odoo_id,
                ],
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
            ],
            'city' => [
                'provided_city_id' => $validatedData['city_id'] ?? null,
                'resolved_city_id' => $city?->id,
                'city_name' => $city?->name,
                'city_odoo_id' => $cityOdooId,
                'location_id' => $validatedData['location_id'] ?? null,
                'location_odoo_id' => $location?->odoo_id ?? null,
            ],
        ]);

        $normalizedGccImport = $this->normalizeGccImport($validatedData['gcc_import'] ?? $carListing?->gcc_import ?? null);

        // Prepare car_info with all required fields - Use Odoo IDs only
        // Ensure Odoo IDs are properly cast to integers
        $brandOdooId = (int) $brand->odoo_id;
        $modelOdooId = (int) $model->odoo_id;
        $bodyTypeOdooId = (int) $bodyType->odoo_id;
        
        // Log what we're sending to Odoo for debugging
        Log::info('Sending Odoo IDs to API', [
            'brand' => [
                'laravel_id' => $brand->id,
                'odoo_id' => $brandOdooId,
                'original_odoo_id' => $brand->odoo_id,
                'name' => $brand->name
            ],
            'model' => [
                'laravel_id' => $model->id,
                'odoo_id' => $modelOdooId,
                'original_odoo_id' => $model->odoo_id,
                'name' => $model->name
            ],
            'body_type' => [
                'laravel_id' => $bodyType->id,
                'odoo_id' => $bodyTypeOdooId,
                'original_odoo_id' => $bodyType->odoo_id,
                'name' => $bodyType->name
            ]
        ]);
        
        $carInfo = [
            'vin' => $validatedData['vin'] ?? '',
            'brand' => $brandOdooId, // Use Odoo ID only (cast to int)
            'model' => $modelOdooId, // Use Odoo ID only (cast to int)
            'condition' => $validatedData['condition_type'],
            'year' => $validatedData['year'],
            'gcc_import' => $normalizedGccImport ?? '',
            'body_type' => $bodyTypeOdooId, // Use Odoo ID only (cast to int)
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
            'trim' => $validatedData['trim'] ?? $carListing?->trim ?? '',
            'damaged_areas' => $validatedData['damaged_areas'] ?? $carListing?->damaged_areas ?? [],
        ];

        // Add inspection file name if available
        if ($inspectionFileName) {
            $carInfo['inspection_file_name'] = $inspectionFileName;
        }

        return [
            'partner_id' => $user->partner_id,
            'city_id' => $cityOdooId,
            'car_info' => $carInfo,
            'price_currency' => $validatedData['price_currency'],
            'price' => $validatedData['price'],
            'seller_info' => [
                'type' => $validatedData['seller_type'] ?? 'private_seller',
            ],
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? '',
            'images' => $imageUrls,
            'inspection' => $inspectionData,
            'inspection_file' => $request->file('inspection_file') ?? null,
            'inspection_file_name' => $inspectionFileName,
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
     * Extract Odoo response data for storage
     */
    private function extractOdooResponseData(array $response): array
    {
        $data = data_get($response, 'result.data', []);
        
        return [
            'listing_id' => $data['listing_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'seller_id' => $data['seller_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'listing_state' => $data['listing_state'] ?? null,
            'listing_name' => $data['listing_name'] ?? null,
            'vehicle_name' => $data['vehicle_name'] ?? null,
            'seller_name' => $data['seller_name'] ?? null,
            'city_name' => $data['city_name'] ?? null,
            'price' => $data['price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'create_date' => $data['create_date'] ?? null,
        ];
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
            // Get user once and eager load relationships to avoid N+1 queries
            $user = $request->user();
            $user->loadMissing(['activeSubscription.subscriptionPlan']);
            
            // Check if user can create listing based on subscription or free tier
            $canCreate = $this->subscriptionService->canCreateListing($user);
            
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
            $validatedData['user_id'] = $user->id;

            // Validate photo count if images are provided
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                // Ensure images is always an array
                if (!is_array($images)) {
                    $images = [$images];
                }
                
                $photoCount = count($images);
                $canUpload = $this->subscriptionService->canUploadPhotos($user, $photoCount);
                
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
            $promotionData = $this->handlePromotionSelection($validatedData, $user);
            $carListingData = array_merge($carListingData, $promotionData);

            // Upload images first to get URLs (without creating media records yet)
            $uploadedImageUrls = [];
            $uploadedImageFiles = [];
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                if (!is_array($images)) {
                    $images = [$images];
                }
                $uploadedImageFiles = $this->uploadImagesToGetUrls($images);
                $uploadedImageUrls = array_column($uploadedImageFiles, 'url');
            }

            // Prepare data for Odoo API FIRST (before creating Laravel listing)
            $odooData = $this->prepareOdooDataFromForm(null, $validatedData, $request, $uploadedImageUrls);

            // Check if Odoo data preparation failed
            if (isset($odooData['error']) && $odooData['error']) {
                DB::rollBack();
                // Clean up uploaded images if any
                $this->cleanupUploadedImages($uploadedImageFiles);
                return $this->errorResponse(
                    $odooData['message'],
                    422,
                    [
                        'missing_odoo_ids' => $odooData['missing_odoo_ids'] ?? [],
                        'details' => $odooData['details'] ?? null,
                        'odoo_response' => null, // No Odoo call made due to preparation failure
                        'reason' => 'odoo_preparation_failed'
                    ]
                );
            }

            // Send to Odoo API FIRST - Only create Laravel listing if Odoo succeeds
            $odooResponse = $this->sendToOdoo($odooData);

            // Check if Odoo API call was successful and returned a valid odoo_id
            $odooId = null;
            $odooResponseData = null;
            
            if ($odooResponse && !isset($odooResponse['error'])) {
                $odooId = $this->extractOdooListingId($odooResponse);
                if ($odooId) {
                    $odooResponseData = $this->extractOdooResponseData($odooResponse);
                }
            }

            // If Odoo failed or didn't return odoo_id, rollback and don't save to Laravel
            if (!$odooId) {
                DB::rollBack();
                // Clean up uploaded images if any
                $this->cleanupUploadedImages($uploadedImageFiles);
                
                // Extract friendly error message from Odoo response
                $errorMessage = 'Failed to create listing. Please try again.';
                $errorDetails = null;
                
                if ($odooResponse && isset($odooResponse['result'])) {
                    $result = $odooResponse['result'];
                    
                    if (isset($result['error'])) {
                        $error = $result['error'];
                        $errorCode = $result['error_code'] ?? null;
                        $details = $result['details'] ?? [];
                        
                        // Handle NOT_FOUND errors with friendly messages
                        if ($errorCode === 'NOT_FOUND') {
                            $resource = $details['resource'] ?? 'Resource';
                            $resourceId = $details['id'] ?? 'unknown';
                            
                            // Map to user-friendly messages
                            $resourceMap = [
                                'Body Type' => 'body type',
                                'Car Brand' => 'car brand',
                                'Car Model' => 'car model',
                                'City' => 'city',
                                'Fuel Type' => 'fuel type',
                                'Transmission' => 'transmission',
                            ];
                            
                            $friendlyResource = $resourceMap[$resource] ?? strtolower($resource);
                            $errorMessage = "The selected {$friendlyResource} is not valid. Please select a different option and try again.";
                            $errorDetails = [
                                'resource' => $resource,
                                'resource_id' => $resourceId,
                                'suggestion' => 'Please ensure the selected option exists in the system'
                            ];
                        } else {
                            // Use the error message if it's user-friendly
                            if (strlen($error) < 200 && !str_contains($error, 'Exception')) {
                                $errorMessage = $error;
                            }
                        }
                    }
                } elseif ($odooResponse === null) {
                    $errorMessage = 'Unable to connect to the server. Please check your connection and try again.';
                } elseif (isset($odooResponse['error'])) {
                    $errorMessage = $odooResponse['message'] ?? $errorMessage;
                }
                
                Log::error('Car listing creation failed: Odoo API did not return valid odoo_id', [
                    'odoo_response' => $odooResponse,
                    'odoo_id_extracted' => $odooId,
                    'user_id' => $request->user()->id,
                    'odoo_data_sent' => $odooData,
                    'friendly_error' => $errorMessage,
                    'error_details' => $errorDetails
                ]);
                
                return $this->errorResponse(
                    $errorMessage,
                    422,
                    [
                        'odoo_response' => $odooResponse ?? null,
                        'odoo_id' => $odooId,
                        'reason' => 'odoo_save_failed',
                        'error_details' => $errorDetails,
                        'odoo_response_full' => $odooResponse // Include full response for debugging
                    ]
                );
            }

            // Odoo succeeded - Now create the listing in Laravel with odoo_id included
            $carListingData['odoo_id'] = $odooId;
            if ($odooResponseData) {
                $carListingData['odoo_listing_id'] = $odooResponseData['listing_id'] ?? null;
                $carListingData['odoo_vehicle_id'] = $odooResponseData['vehicle_id'] ?? null;
                $carListingData['odoo_seller_id'] = $odooResponseData['seller_id'] ?? null;
                $carListingData['odoo_city_id'] = $odooResponseData['city_id'] ?? null;
                $carListingData['odoo_listing_state'] = $odooResponseData['listing_state'] ?? null;
            }

            // Create the car listing in local database with odoo_id
            $carListing = CarListing::create($carListingData);

            // Consume slot if user is free tier (no active subscription)
            $this->subscriptionService->consumeSlot($request->user());

            // Now attach images to the listing by creating media records
            if (!empty($uploadedImageFiles)) {
                $this->attachImagesToListing($carListing, $uploadedImageFiles);
            }

            // Handle inspection data and file if provided
            if ($this->hasInspectionData($validatedData) || $request->hasFile('inspection_file')) {
                $this->handleInspectionFormData($carListing, $validatedData, $request);
            }

            Log::info('Car listing created successfully with Odoo ID', [
                'listing_id' => $carListing->id,
                'odoo_id' => $odooId,
                'odoo_data' => $odooResponseData
            ]);

            DB::commit();

            // Send notification to user about successful listing creation
            $this->sendListingCreatedNotification($carListing, $request->user());

            // Get subscription info for response
            $subscriptionInfo = $this->subscriptionService->canCreateListing($request->user());

            // Load all necessary relationships for comprehensive response
            $carListing->load([
                'brand', 'carModel', 'bodyType', 'city', 'studio',
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
                'odoo_response' => $odooResponse, // Full Odoo response for debugging
                'odoo_id' => $odooId,
                'odoo_response_data' => $odooResponseData // Parsed Odoo response data
            ], 'Car listing created successfully and is pending approval');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded images if any were uploaded before the exception
            if (isset($uploadedImageFiles) && !empty($uploadedImageFiles)) {
                $this->cleanupUploadedImages($uploadedImageFiles);
            }
            
            Log::error('Car listing creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null,
                'odoo_response' => $odooResponse ?? null
            ]);
            
            return $this->errorResponse(
                'Failed to create car listing: ' . $e->getMessage(),
                500,
                [
                    'odoo_response' => $odooResponse ?? null, // Include Odoo response if available
                    'exception' => get_class($e)
                ]
            );
        }
    }

    /**
     * Map form data to car listing database structure (Flutter compatible)
     */
    private function mapFormDataToCarListingData(array $data): array
    {
        $normalizedGccImport = $this->normalizeGccImport($data['gcc_import'] ?? null);

        return [
            'vin' => $data['vin'] ?? null,
            'car_brand_id' => $data['car_brand_id'],
            'car_model_id' => $data['car_model_id'],
            'condition_type' => $data['condition_type'],
            'car_condition_id' => $this->getCarConditionIdFromConditionType($data['condition_type'] ?? null),
            'year' => $data['year'],
            'gcc_import' => $normalizedGccImport,
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
            'damaged_areas' => $data['damaged_areas'] ?? [],
            'doors' => $data['doors'] ?? null,
            'trim' => $data['trim'] ?? null,
            'origin_country' => $data['origin_country'] ?? $normalizedGccImport ?? null,
            'is_inspected' => $data['is_inspected'] ?? false,
            'is_verified' => $data['is_verified'] ?? false,
            'has_insurance' => $data['has_insurance'] ?? false,
            'city_id' => $data['city_id'] ?? null,
            'location_id' => $this->getOrCreateLocationIdFromFormData($data),
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
            'features' => $data['features'] ?? null,
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
    private function handleImageUploads(CarListing $carListing, $images): array
    {
        // Ensure images is always an array
        if (!is_array($images)) {
            $images = [$images];
        }

        $uploadedUrls = [];
        
        foreach ($images as $index => $image) {
            try {
                // Use HandlesFileUpload trait for S3 upload
                $uploadResult = $this->uploadFileToS3($image, $carListing, 'car-listings');
                
                Log::info('S3 upload result', [
                    'car_listing_id' => $carListing->id,
                    'upload_result' => $uploadResult
                ]);
                
                // Create media record with proper structure
                $mediaData = [
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'media' => $uploadResult['file_name'],
                    'path_file_name' => $uploadResult['path'],
                    'file_type' => $uploadResult['file_type'],
                    'info' => $index === 0 ? 'primary' : 'secondary',
                ];
                
                // Only add order if the column exists
                if (Schema::hasColumn('media', 'order')) {
                    $mediaData['order'] = $index;
                }
                
                $media = Media::create($mediaData);
                $uploadedUrls[] = $media->public_url ?? $uploadResult['url'] ?? $media->path_file_name;
                
                Log::info('Media record created successfully', [
                    'media_id' => $media->id,
                    'car_listing_id' => $carListing->id,
                    'file_name' => $uploadResult['file_name'],
                    'url' => $uploadResult['url']
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to upload image for car listing', [
                    'car_listing_id' => $carListing->id,
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with other images even if one fails
            }
        }

        return $uploadedUrls;
    }

    /**
     * Upload images to S3 and return URLs without creating media records
     * This is used before saving to Odoo, so we can send image URLs to Odoo
     */
    private function uploadImagesToGetUrls(array $images): array
    {
        $uploadedFiles = [];
        
        foreach ($images as $index => $image) {
            try {
                // Upload directly to S3 without needing a model
                $filename = time() . '_' . Str::random(10) . '_' . $image->getClientOriginalName();
                $directory = 'car-listings/temp';
                
                $path = Storage::disk('s3')->putFileAs(
                    $directory,
                    $image,
                    $filename,
                    'public'
                );

                if (!$path) {
                    Log::error('Failed to upload image to S3', [
                        'index' => $index,
                        'filename' => $filename
                    ]);
                    continue;
                }

                // Generate temporary URL that's publicly accessible (valid for 7 days)
                // This ensures Odoo can access the image when processing the request
                $url = Storage::disk('s3')->temporaryUrl($path, now()->addDays(7));
                $mappedType = $this->mapMimeTypeToEnum($image->getClientMimeType());

                $uploadedFiles[] = [
                    'file_name' => $filename,
                    'path' => $path,
                    'url' => $url,
                    'file_type' => $mappedType,
                    'index' => $index,
                ];

                Log::info('Image uploaded to S3 (temporary)', [
                    'index' => $index,
                    'path' => $path,
                    'url' => $url
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to upload image to S3', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with other images even if one fails
            }
        }

        return $uploadedFiles;
    }

    /**
     * Attach already uploaded images to a car listing by creating media records
     */
    private function attachImagesToListing(CarListing $carListing, array $uploadedImageFiles): void
    {
        foreach ($uploadedImageFiles as $index => $fileInfo) {
            try {
                // Create media record with proper structure
                $mediaData = [
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'media' => $fileInfo['file_name'],
                    'path_file_name' => $fileInfo['path'],
                    'file_type' => $fileInfo['file_type'],
                    'info' => ($fileInfo['index'] ?? $index) === 0 ? 'primary' : 'secondary',
                ];

                // Only add order if the column exists
                if (Schema::hasColumn('media', 'order')) {
                    $mediaData['order'] = $fileInfo['index'] ?? $index;
                }

                $media = Media::create($mediaData);

                Log::info('Media record created successfully for listing', [
                    'media_id' => $media->id,
                    'car_listing_id' => $carListing->id,
                    'file_name' => $fileInfo['file_name'],
                    'url' => $fileInfo['url']
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to create media record for listing', [
                    'car_listing_id' => $carListing->id,
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with other images even if one fails
            }
        }
    }

    /**
     * Clean up uploaded images from S3 if Odoo save fails
     */
    private function cleanupUploadedImages(array $uploadedImageFiles): void
    {
        foreach ($uploadedImageFiles as $fileInfo) {
            try {
                if (isset($fileInfo['path']) && Storage::disk('s3')->exists($fileInfo['path'])) {
                    Storage::disk('s3')->delete($fileInfo['path']);
                    Log::info('Cleaned up uploaded image from S3', [
                        'path' => $fileInfo['path']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to cleanup uploaded image from S3', [
                    'path' => $fileInfo['path'] ?? null,
                    'error' => $e->getMessage()
                ]);
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
        if (!$fuelType) return '';
        
        // Map fuel type names to Odoo-compatible values
        $fuelTypeMapping = [
            'Petrol' => 'gasoline',
            'Diesel' => 'diesel', 
            'Electric' => 'electric',
            'Hybrid' => 'hybrid'
        ];
        
        return $fuelTypeMapping[$fuelType->name] ?? strtolower($fuelType->name);
    }

    /**
     * Map fuel type string to Odoo-compatible value
     */
    private function mapFuelTypeForOdoo(string $fuelType): string
    {
        if (empty($fuelType)) return '';
        
        // Map fuel type names to Odoo-compatible values
        $fuelTypeMapping = [
            'petrol' => 'gasoline',
            'Petrol' => 'gasoline',
            'diesel' => 'diesel',
            'Diesel' => 'diesel',
            'electric' => 'electric',
            'Electric' => 'electric',
            'hybrid' => 'hybrid',
            'Hybrid' => 'hybrid'
        ];
        
        return $fuelTypeMapping[$fuelType] ?? strtolower($fuelType);
    }

    /**
     * Get or create location ID based on city_id
     */
    private function getOrCreateLocationId(array $validatedData): int
    {
        // If city_id is provided, find or create a location for it
        if (isset($validatedData['car_info']['city_id']) && $validatedData['car_info']['city_id']) {
            $cityId = $validatedData['car_info']['city_id'];
            $city = \App\Models\City::find($cityId);
            
            if ($city) {
                // Try to find existing location for this city
                $location = \App\Models\Location::where('name', $city->name)->first();
                
                if (!$location) {
                    // Create new location if none exists
                    $location = \App\Models\Location::create([
                        'name' => $city->name,
                        'odoo_id' => $city->odoo_id
                    ]);
                }
                
                return $location->id;
            }
        }
        
        // Fallback to first available location or create a default one
        $defaultLocation = \App\Models\Location::first();
        
        if (!$defaultLocation) {
            // Create a default location if none exists
            $defaultLocation = \App\Models\Location::create([
                'name' => 'Default Location',
                'odoo_id' => 'default'
            ]);
        }
        
        return $defaultLocation->id;
    }

    /**
     * Get or create location ID from form data
     */
    private function getOrCreateLocationIdFromFormData(array $data): int
    {
        // If city_id is provided, find or create a location for it
        if (isset($data['city_id']) && $data['city_id']) {
            $cityId = $data['city_id'];
            $city = \App\Models\City::find($cityId);
            
            if ($city) {
                // Try to find existing location for this city
                $location = \App\Models\Location::where('name', $city->name)->first();
                
                if (!$location) {
                    // Create new location if none exists
                    $location = \App\Models\Location::create([
                        'name' => $city->name,
                        'odoo_id' => $city->odoo_id
                    ]);
                }
                
                return $location->id;
            }
        }
        
        // Fallback to first available location or create a default one
        $defaultLocation = \App\Models\Location::first();
        
        if (!$defaultLocation) {
            // Create a default location if none exists
            $defaultLocation = \App\Models\Location::create([
                'name' => 'Default Location',
                'odoo_id' => 'default'
            ]);
        }
        
        return $defaultLocation->id;
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

    /**
     * Normalize GCC import value to consistent string representation
     */
    private function normalizeGccImport($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'GCC' : 'Import';
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        $upperValue = strtoupper($stringValue);

        $mapping = [
            '1' => 'GCC',
            'TRUE' => 'GCC',
            'YES' => 'GCC',
            'GCC' => 'GCC',
            'GCC SPEC' => 'GCC',
            'GCC-SPEC' => 'GCC',
            'GCC_SPEC' => 'GCC',
            '0' => 'Import',
            'FALSE' => 'Import',
            'NO' => 'Import',
            'NON GCC' => 'Import',
            'NON-GCC' => 'Import',
            'NON_GCC' => 'Import',
            'IMPORT' => 'Import',
            'IMPORTED' => 'Import',
        ];

        if (isset($mapping[$upperValue])) {
            return $mapping[$upperValue];
        }

        // Preserve known regional strings (e.g., American, Japanese)
        return $stringValue;
    }

    /**
     * Send notification to user about successful listing creation
     */
    private function sendListingCreatedNotification(CarListing $carListing, $user)
    {
        try {
            // Reload listing with relationships for notification
            $carListing->load(['brand', 'carModel']);
            
            // Use UnifiedNotificationService to create notification (database + optional Firebase)
            $this->notificationService->createListingCreatedNotification($carListing);

            Log::info('Listing created notification sent', [
                'user_id' => $user->id,
                'car_listing_id' => $carListing->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send listing created notification', [
                'user_id' => $user->id,
                'car_listing_id' => $carListing->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}