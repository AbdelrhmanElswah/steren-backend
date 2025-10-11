<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CarListingCreateRequest;
use App\Models\CarListing;
use App\Models\Media;
use App\Services\Odoo\CarListingService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarListingApiController extends Controller
{
    use ApiResponse;

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

            // Create the car listing in local database
            $carListing = CarListing::create($carListingData);

            // Consume slot if user is free tier (no active subscription)
            // This must happen after listing creation to ensure slot is consumed
            $this->subscriptionService->consumeSlot($request->user());

            // Handle images if provided
            if ($request->has('images') && is_array($request->images)) {
                $this->handleImages($carListing, $request->images);
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

            return $this->createdResponse([
                'car_listing' => $carListing->load(['brand', 'carModel', 'bodyType', 'media']),
                'status' => 'pending',
                'message' => 'Car listing created successfully and is pending approval from our team.',
                'subscription_info' => [
                    'remaining_listings' => $subscriptionInfo['remaining_listings'],
                    'max_listings' => $subscriptionInfo['max_listings']
                ],
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

                // Store image
                Storage::disk('public')->put($path, $imageData);

                // Create media record
                Media::create([
                    'modelable_type' => CarListing::class,
                    'modelable_id' => $carListing->id,
                    'file_path' => $path,
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
                Log::error('Odoo API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'data' => $data
                ]);
                return null;
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
}