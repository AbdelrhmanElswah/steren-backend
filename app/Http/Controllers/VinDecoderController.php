<?php

namespace App\Http\Controllers;

use App\Http\Requests\VinDecodeRequest;
use App\Http\Resources\VinDecoderResource;
use App\Services\VehicleDatabasesService;
use App\Exceptions\VehicleDatabasesException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VinDecoderController extends Controller
{
    use ApiResponse;

    protected VehicleDatabasesService $vehicleDatabasesService;

    public function __construct(VehicleDatabasesService $vehicleDatabasesService)
    {
        $this->vehicleDatabasesService = $vehicleDatabasesService;
    }

    /**
     * Decode VIN using Vehicle Databases API
     *
     * @param VinDecodeRequest $request
     * @return JsonResponse
     */
    public function decode(VinDecodeRequest $request): JsonResponse
    {
        try {
            $vin = $request->validated()['vin'];
            $cache = $request->validated()['cache'] ?? true;
            $saveToDatabase = $request->validated()['save_to_database'] ?? false;

            if ($saveToDatabase) {
                $result = $this->vehicleDatabasesService->decodeAndSaveVin($vin, true);

                return $this->successResponse(
                    new VinDecoderResource($result),
                    'VIN decoded and saved successfully'
                );
            }

            $result = $this->vehicleDatabasesService->decodeVin($vin, $cache);

            return $this->successResponse($result['data'], 'VIN decoded successfully');

        } catch (VehicleDatabasesException $e) {
            Log::error('VIN decode failed', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN decode', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while decoding the VIN',
                500
            );
        }
    }

    /**
     * Get VIN information from database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVinInfo(Request $request): JsonResponse
    {
        $vin = $request->input('vin');

        if (!$vin) {
            return $this->errorResponse('VIN is required', 400);
        }

        try {
            $vinDecoder = \App\Models\VinDecoder::byVin($vin)->first();

            if (!$vinDecoder) {
                return $this->errorResponse('VIN not found in database', 404);
            }

            return $this->successResponse(
                new VinDecoderResource($vinDecoder),
                'VIN information retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving VIN info', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving VIN information',
                500
            );
        }
    }

    /**
     * Get service status
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->vehicleDatabasesService->getStatus();

            return $this->successResponse($status, 'Service status retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error getting service status', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving service status',
                500
            );
        }
    }

    /**
     * Decode VIN and return filtered data for car listing creation
     *
     * @param VinDecodeRequest $request
     * @return JsonResponse
     */
    public function decodeForListing(VinDecodeRequest $request): JsonResponse
    {
        try {
            $vin = $request->validated()['vin'];
            $cache = $request->validated()['cache'] ?? true;

            // Get the full VIN decode data
            $result = $this->vehicleDatabasesService->decodeVin($vin, $cache);

            if (!$result || !isset($result['data'])) {
                return $this->errorResponse('Failed to decode VIN', 400);
            }

            $vinData = $result['data'];

            // Filter and map the data for car listing creation
            $filteredData = $this->filterVinDataForListing($vinData);

            // Check if the brand exists in our system
            if (!$this->validateBrandExists($filteredData)) {
                // Add debugging information
                $extractedBrand = $filteredData['general']['make'] ?? 'Unknown';
                $brandId = $filteredData['brand_id'] ?? 'Not found';
                
                return response()->json([
                    "success" => false,
                    "message" => "This vehicle brand is not supported in our system",
                    "debug" => [
                        "extracted_brand" => $extractedBrand,
                        "brand_id" => $brandId,
                        "available_brands" => \App\Models\CarBrand::pluck('name', 'id')->toArray()
                    ],
                    "data" => null
                ], 422);
            }

            return $this->successResponse($filteredData, 'VIN decoded and filtered for listing creation');

        } catch (VehicleDatabasesException $e) {
            Log::error('VIN decode for listing failed', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN decode for listing', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while decoding the VIN',
                500
            );
        }
    }

    /**
     * Extract VIN from image using OCR and decode it for listing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ocrAndDecodeForListing(Request $request): JsonResponse
    {
        try {
            // Validate file upload
            $request->validate([
                'file' => 'required|file|mimes:png,jpg,jpeg|max:10240'
            ]);

            $file = $request->file('file');

            // Step 1: Extract VIN from image using OCR
            $vinResult = $this->extractVinFromImage($file);

            if (!$vinResult['success']) {
                return $this->errorResponse(
                    'Unable to extract VIN from image: ' . $vinResult['message'],
                    400
                );
            }

            $vin = $vinResult['data']['vin'];

            // Step 2: Decode the extracted VIN
            $result = $this->vehicleDatabasesService->decodeVin($vin, true);

            if (!$result || !isset($result['data'])) {
                return $this->errorResponse('Failed to decode VIN', 400);
            }

            $vinData = $result['data'];

            // Step 3: Filter and map the data for car listing creation
            $filteredData = $this->filterVinDataForListing($vinData);

            // Step 4: Check if the brand exists in our system
            if (!$this->validateBrandExists($filteredData)) {
                return response()->json([
                    "success" => false,
                    "message" => "This vehicle brand is not supported in our system",
                    "data" => null
                ], 422);
            }

            return $this->successResponse($filteredData, 'VIN extracted from image and decoded successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Invalid file: ' . implode(', ', $e->errors()['file']),
                400
            );
        } catch (VehicleDatabasesException $e) {
            Log::error('VIN OCR and decode failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN OCR and decode', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while processing the image',
                500
            );
        }
    }

    /**
     * Extract VIN from image using OCR
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    private function extractVinFromImage($file): array
    {
        try {
            // Store file temporarily
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $response = Http::timeout(30)
                ->attach('file', fopen($fullPath, 'r'), $file->getClientOriginalName())
                ->post(config('services.vin_ocr.base_url') . '/vin-ocr');

            // Clean up temp file
            Storage::disk('local')->delete($tempPath);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'success' && isset($data['data']['vin'])) {
                    return [
                        'success' => true,
                        'data' => [
                            'vin' => $data['data']['vin']
                        ]
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to extract VIN from image'
            ];

        } catch (\Exception $e) {
            Log::error('VIN OCR extraction failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'OCR service unavailable'
            ];
        }
    }

    /**
     * Validate if the brand exists in our system
     *
     * @param array $filteredData
     * @return bool
     */
    private function validateBrandExists(array $filteredData): bool
    {
        // Check if brandId exists and is not null (now at root level)
        if (empty($filteredData['brand_id'])) {
            return false;
        }

        // Verify the brand exists in our database
        $brand = \App\Models\CarBrand::find($filteredData['brand_id']);

        return $brand !== null;
    }

    /**
     * Filter VIN data to match car listing creation format
     *
     * @param array $vinData
     * @return array
     */
    private function filterVinDataForListing(array $vinData): array
    {
        $additionalData = $vinData['additional_data']['data'] ?? [];
        $basicData = $vinData['data'] ?? [];

        // Extract basic vehicle information from either structure
        $make = $additionalData['make'] ?? $basicData['make'] ?? '';
        $model = $additionalData['model'] ?? $basicData['model'] ?? '';
        $year = $additionalData['year'] ?? $basicData['year'] ?? $vinData['year'] ?? null;
        $style = $additionalData['style'] ?? $basicData['style'] ?? '';

        // Extract trim directly from additional_data only (no default values)
        $trim = $additionalData['trim'] ?? $basicData['trim'] ?? null;
        
        $bodyType = $this->extractBodyTypeFromStyle($style);
        $transmission = $this->extractTransmissionFromStyle($style);
        $driveType = $this->extractDriveTypeFromStyle($style);

        // Extract additional specifications from the nested data
        $specifications = $additionalData['specifications'] ?? [];
        $dimensions = $additionalData['dimensions'] ?? [];
        $vehicle = $additionalData['vehicle'] ?? $basicData['vehicle'] ?? [];
        $standardFeatures = $basicData['standard_features'] ?? [];

        // Extract fuel type from specifications (more accurate source)
        $fuelType = $this->extractFuelTypeFromSpecifications($specifications);
        if (!$fuelType) {
            $fuelType = $basicData['fuel_type'] ?? null;
        }
        
        // Extract cylinders from specifications (more accurate source)
        $cylinders = $this->extractCylindersFromSpecifications($specifications);
        if (!$cylinders) {
            $cylinders = $basicData['cylinders'] ?? $vinData['cylinders'] ?? null;
        }
        
        $exteriorColor = $basicData['exterior_color'] ?? $vinData['exterior_color'] ?? null;
        $doors = $basicData['doors'] ?? $vinData['doors'] ?? null;
        $engineSize = $basicData['engine_size'] ?? $vinData['engine_size'] ?? null;
        $seatingCapacity = $basicData['seating_capacity'] ?? $vinData['seating_capacity'] ?? null;

        // Extract available colors from both additional data and basic data
        $availableColors = $this->extractAvailableColors($additionalData);
        if (empty($availableColors)) {
            $availableColors = $this->extractAvailableColors($basicData);
        }

        // Set primary exterior color to the first available color (usually the standard/default color)
        if (!empty($availableColors)) {
            $exteriorColor = $availableColors[0]['name'];
        }

        // Extract performance data
        $horsepower = $this->extractHorsepowerFromDimensions($dimensions);
        $torque = $this->extractTorqueFromDimensions($dimensions);
        $acceleration = $this->extractAcceleration($specifications);
        $topSpeed = $this->extractTopSpeed($specifications);
        $mileageEfficiency = $this->extractMileageEfficiency($specifications);

        // Extract engine size (for electric vehicles, this might be battery capacity)
        $engineSize = $this->extractEngineSize($specifications, $dimensions);

        // Extract additional data from standard features
        $extractedEngineSize = $this->extractEngineSizeFromFeatures($standardFeatures);
        $extractedHorsepower = $this->extractHorsepowerFromFeatures($standardFeatures);
        $extractedTorque = $this->extractTorqueFromFeatures($standardFeatures);
        $extractedCylinders = $this->extractCylindersFromFeatures($standardFeatures);

        // Extract standard options for additional data
        $standardOptions = $basicData['standard_options'] ?? [];
        $extractedSeatingCapacity = $this->extractSeatingCapacityFromFeatures($standardFeatures, $standardOptions);

        // Use extracted data if available
        $engineSize = $extractedEngineSize ?? $engineSize;
        $horsepower = $extractedHorsepower ?? $horsepower;
        $torque = $extractedTorque ?? $torque;
        $cylinders = $extractedCylinders ?? $cylinders;
        $seatingCapacity = $extractedSeatingCapacity ?? $seatingCapacity;

        // Extract dimensions
        $length = $this->extractDimension($dimensions, 'overall_length');
        $width = $this->extractDimension($dimensions, 'overall_width');
        $height = $this->extractDimension($dimensions, 'overall_height');

        // Extract seating capacity from specifications
        $seatingCapacity = $this->extractSeatingCapacity($specifications) ?? $seatingCapacity;
        // Extract doors from vehicle data or basic data
        $doors = $vehicle['doors'] ?? $basicData['doors'] ?? $doors;

        // Extract body type from vehicle data (more reliable than style parsing)
        $bodyType = $vehicle['body_type'] ?? $bodyType;

        // Get brand and model IDs from database
        $brandId = $this->getBrandIdByName($make);
        $modelId = $this->getModelIdByName($model, $brandId);
        $bodyTypeId = $this->getBodyTypeIdByName($bodyType);
        $bodyTypeName = $this->getBodyTypeNameById($bodyTypeId);

        // Map fuel type to match your system
        $mappedFuelType = $this->mapFuelType($fuelType);

        // Map transmission to match your system
        $mappedTransmission = $this->mapTransmission($transmission);

        // Map drive type to match your system
        $mappedDriveType = $this->mapDriveType($driveType);

        // Extract transmission gears from specifications or features
        $transmissionGears = $this->extractTransmissionGears($specifications, $standardFeatures);
        
        // Extract GCC/Import information
        $gccImport = $this->extractGccImport($vinData, $additionalData, $basicData);
        
        // Extract damaged parts information
        $damagedParts = $this->extractDamagedParts($vinData, $additionalData, $basicData);
        
        // Build response structured to match UI design
        $response = [
            'general' => [],
            'engine' => []
        ];
        
        // General Section
        if (!empty($basicData['vin'] ?? $vinData['vin'])) {
            $response['general']['vin'] = $basicData['vin'] ?? $vinData['vin'];
        }
        
        if (!empty($make)) {
            $response['general']['make'] = $make;
        }
        
        if (!empty($model)) {
            $response['general']['model'] = $model;
        }
        
        if ($year) {
            $response['general']['model_year'] = (string)$year;
        }
        
        if ($trim !== null && $trim !== '') {
            $response['general']['trim'] = (string)$trim;
        }
        
        // GCC/Import field location: response.general.gcc_import
        // Returns "GCC" for GCC countries, "Import" for others, or null if cannot be determined
        if (!empty($gccImport)) {
            $response['general']['gcc_import'] = $gccImport;
        }
        
        if (!empty($bodyTypeName)) {
            $response['general']['body_type'] = $bodyTypeName;
        }
        
        if ($seatingCapacity) {
            $response['general']['seating_capacity'] = $seatingCapacity;
        }
        
        if ($damagedParts !== null) {
            $response['general']['damaged_parts'] = $damagedParts;
        }
        
        // Engine Section
        if (!empty($mappedFuelType)) {
            $response['engine']['engine_type'] = $mappedFuelType;
        }
        
        if ($engineSize) {
            $response['engine']['engine_size'] = $engineSize;
        }
        
        if ($cylinders) {
            $response['engine']['engine_cylinders'] = $cylinders;
        }
        
        if (!empty($mappedTransmission)) {
            $response['engine']['transmission'] = $mappedTransmission;
        }
        
        if ($transmissionGears) {
            $response['engine']['transmission_gears'] = $transmissionGears;
        }
        
        if (!empty($mappedDriveType)) {
            $response['engine']['drive_type'] = $mappedDriveType;
        }
        
        if (!empty($mappedFuelType)) {
            $response['engine']['fuel_type'] = $mappedFuelType;
        }
        
        // Additional fields for backward compatibility and extended data
        if ($brandId) {
            $response['brand_id'] = (string)$brandId;
            $response['brand_logo_url'] = $this->getBrandLogoUrl($brandId);
        }
        
        if ($modelId) {
            $response['model_id'] = (string)$modelId;
        }
        
        if (!empty($exteriorColor)) {
            $response['exterior_color'] = $exteriorColor;
        }
        
        if ($doors) {
            $response['doors'] = $doors;
        }
        
        if ($horsepower) {
            $response['horsepower'] = $horsepower;
        }
        
        if ($torque) {
            $response['torque'] = $torque;
        }
        
        if ($acceleration) {
            $response['acceleration_0_100'] = $acceleration;
        }
        
        if ($topSpeed) {
            $response['top_speed'] = $topSpeed;
        }
        
        if ($mileageEfficiency) {
            $response['mileage_efficiency'] = $mileageEfficiency;
        }
        
        if ($length) {
            $response['length'] = $length;
        }
        
        if ($width) {
            $response['width'] = $width;
        }
        
        if ($height) {
            $response['height'] = $height;
        }
        
        return $response;
    }

    /**
     * Extract trim from style string
     */
    private function extractTrimFromStyle(string $style): string
    {
        // Example: "4dr Rear-Wheel Drive Sedan Automatic"
        // Extract the trim part (everything before "Sedan")
        if (preg_match('/^(.+?)\s+(Sedan|SUV|Hatchback|Coupe|Convertible)/i', $style, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Extract body type from style string
     */
    private function extractBodyTypeFromStyle(string $style): ?string
    {
        if (preg_match('/(Sedan|SUV|Hatchback|Coupe|Convertible|Truck|Van)/i', $style, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract transmission from style string
     */
    private function extractTransmissionFromStyle(string $style): ?string
    {
        if (preg_match('/(Automatic|Manual|CVT)/i', $style, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract drive type from style string
     */
    private function extractDriveTypeFromStyle(string $style): ?string
    {
        if (preg_match('/(Rear-Wheel Drive|Front-Wheel Drive|All-Wheel Drive|Four-Wheel Drive)/i', $style, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get brand ID by name
     */
    private function getBrandIdByName(string $brandName): ?int
    {
        if (empty($brandName)) {
            return null;
        }

        // Try exact match first
        $brand = \App\Models\CarBrand::whereRaw('LOWER(name) = ?', [strtolower($brandName)])->first();

        if (!$brand) {
            // Try partial matches for common variations
            $variations = $this->getBrandVariations($brandName);
            foreach ($variations as $variation) {
                $brand = \App\Models\CarBrand::whereRaw('LOWER(name) = ?', [strtolower($variation)])->first();
                if ($brand) {
                    break;
                }
            }
        }

        return $brand ? $brand->id : null;
    }

    /**
     * Get model ID by name and brand ID
     */
    private function getModelIdByName(string $modelName, ?int $brandId): ?int
    {
        if (!$brandId || empty($modelName)) {
            return null;
        }

        // Try exact match first
        $model = \App\Models\CarModel::whereRaw('LOWER(name) = ?', [strtolower($modelName)])
            ->where('car_brand_id', $brandId)
            ->first();

        if ($model) {
            return $model->id;
        }

        // Try variations for common model name patterns
        $variations = $this->getModelVariations($modelName);
        foreach ($variations as $variation) {
            $model = \App\Models\CarModel::whereRaw('LOWER(name) = ?', [strtolower($variation)])
                ->where('car_brand_id', $brandId)
                ->first();
            if ($model) {
                return $model->id;
            }
        }

        // Normalize and try strict normalized equality against all brand models
        $normalizedTarget = $this->normalizeModelName($modelName);
        $brandModels = \App\Models\CarModel::where('car_brand_id', $brandId)->get(['id', 'name']);
        foreach ($brandModels as $brandModel) {
            if ($this->normalizeModelName($brandModel->name) === $normalizedTarget) {
                return $brandModel->id;
            }
        }

        // Fallback: fuzzy match by similarity to find closest model for this brand
        $bestMatchId = null;
        $bestScore = 0;
        foreach ($brandModels as $brandModel) {
            $normalizedCandidate = $this->normalizeModelName($brandModel->name);
            similar_text($normalizedTarget, $normalizedCandidate, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestMatchId = $brandModel->id;
            }
        }

        // Accept the best fuzzy match only if similarity is reasonably high
        if ($bestMatchId !== null && $bestScore >= 70) {
            return $bestMatchId;
        }

        return null;
    }

    /**
     * Get body type ID by name
     */
    private function getBodyTypeIdByName(string $bodyTypeName): ?int
    {
        if (empty($bodyTypeName)) {
            return null;
        }

        $bodyType = \App\Models\BodyType::whereRaw('LOWER(name) = ?', [strtolower($bodyTypeName)])->first();

        return $bodyType ? $bodyType->id : null;
    }

    /**
     * Get body type name by ID
     */
    private function getBodyTypeNameById(?int $bodyTypeId): ?string
    {
        if (!$bodyTypeId) {
            return null;
        }

        $bodyType = \App\Models\BodyType::find($bodyTypeId);

        return $bodyType ? $bodyType->name : null;
    }

    /**
     * Get brand logo URL from media
     */
    private function getBrandLogoUrl(?int $brandId): ?string
    {
        if (!$brandId) {
            return null;
        }

        $brand = \App\Models\CarBrand::with('media')->find($brandId);
        
        if (!$brand || !$brand->media) {
            return null;
        }

        $media = $brand->media;
        
        // Use the media's temporary URL accessor (expires in 60 minutes)
        // This uses the getMediaTemporaryUrlAttribute from the Media model
        if (isset($media->media_temporary_url) && $media->media_temporary_url) {
            return $media->media_temporary_url;
        }
        
        // Fallback: generate temporary URL directly if accessor doesn't work
        if ($media->path_file_name) {
            return $media->getS3TemporaryUrl($media->path_file_name, 60);
        }
        
        // Last resort: try public URL
        if ($media->path_file_name) {
            return $media->getS3PublicUrl($media->path_file_name);
        }
        
        return null;
    }

    /**
     * Map fuel type to system format
     */
    private function mapFuelType(?string $fuelType): ?string
    {
        if ($fuelType === null) {
            return null;
        }

        $mapping = [
            'Electric' => 'Electric',
            'Gasoline' => 'Gasoline',
            'Diesel' => 'Diesel',
            'Hybrid' => 'Hybrid',
            'Petrol' => 'Gasoline',
            'Battery Electric' => 'Electric',
            'Plug-in Hybrid' => 'Hybrid',
        ];

        return $mapping[$fuelType] ?? $fuelType;
    }

    /**
     * Map transmission to system format
     */
    private function mapTransmission(?string $transmission): ?string
    {
        if ($transmission === null) {
            return null;
        }

        $mapping = [
            '1-Speed Automatic' => 'Automatic',
            'Automatic' => 'Automatic',
            'Manual' => 'Manual',
            'CVT' => 'CVT',
            'Semi-Automatic' => 'Semi-Automatic',
        ];

        return $mapping[$transmission] ?? $transmission;
    }

    /**
     * Map drive type to system format
     */
    private function mapDriveType(?string $driveType): ?string
    {
        if ($driveType === null) {
            return null;
        }

        $mapping = [
            'Rear-Wheel Drive' => 'RWD',
            'Front-Wheel Drive' => 'FWD',
            'All-Wheel Drive' => 'AWD',
            'Four-Wheel Drive' => '4WD',
            'RWD' => 'RWD',
            'FWD' => 'FWD',
            'AWD' => 'AWD',
            '4WD' => '4WD',
        ];

        return $mapping[$driveType] ?? $driveType;
    }

    /**
     * Extract horsepower from dimensions array
     */
    private function extractHorsepower(array $specifications): ?int
    {
        // Horsepower is actually in dimensions array, not specifications
        return null; // Will be extracted from dimensions in the main method
    }

    /**
     * Extract torque from dimensions array
     */
    private function extractTorque(array $specifications): ?int
    {
        // Torque is actually in dimensions array, not specifications
        return null; // Will be extracted from dimensions in the main method
    }

    /**
     * Extract acceleration data (0-100 km/h or 0-60 mph)
     */
    private function extractAcceleration(array $specifications): ?float
    {
        // This data might not be available in the current API response
        // but we'll keep the method for future use
        return null;
    }

    /**
     * Extract top speed data
     */
    private function extractTopSpeed(array $specifications): ?int
    {
        // This data might not be available in the current API response
        // but we'll keep the method for future use
        return null;
    }

    /**
     * Extract mileage efficiency (MPGe for electric vehicles)
     */
    private function extractMileageEfficiency(array $specifications): ?int
    {
        foreach ($specifications as $spec) {
            if (isset($spec['mpge']['epa_combined_economy'])) {
                $mpge = $spec['mpge']['epa_combined_economy'];
                if (!empty($mpge)) {
                    return (int) $mpge;
                }
            }
        }
        return null;
    }

    /**
     * Extract dimension value from dimensions array
     */
    private function extractDimension(array $dimensions, string $dimensionType): ?float
    {
        foreach ($dimensions as $dimension) {
            if (isset($dimension['exterior'])) {
                foreach ($dimension['exterior'] as $item) {
                    if (isset($item[$dimensionType])) {
                        $dimData = $item[$dimensionType];
                        if (is_array($dimData)) {
                            foreach ($dimData as $unit) {
                                if (isset($unit['unit']) && $unit['unit'] === 'mm' && !empty($unit['value'])) {
                                    // Remove commas and convert to float
                                    $value = str_replace(',', '', $unit['value']);
                                    return (float) $value;
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract seating capacity from specifications
     */
    private function extractSeatingCapacity(array $specifications): ?int
    {
        foreach ($specifications as $spec) {
            if (isset($spec['seating']['number_of_seats'])) {
                $seats = $spec['seating']['number_of_seats'];
                if (!empty($seats)) {
                    return (int) $seats;
                }
            }
        }
        return null;
    }

    /**
     * Extract horsepower from dimensions array
     */
    private function extractHorsepowerFromDimensions(array $dimensions): ?int
    {
        foreach ($dimensions as $dimension) {
            if (isset($dimension['engine'])) {
                foreach ($dimension['engine'] as $engineItem) {
                    if (isset($engineItem['horsepower'])) {
                        $hpData = $engineItem['horsepower'];
                        if (is_array($hpData)) {
                            foreach ($hpData as $item) {
                                if (isset($item['unit']) && $item['unit'] === 'hp' && !empty($item['value'])) {
                                    return (int) $item['value'];
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract torque from dimensions array
     */
    private function extractTorqueFromDimensions(array $dimensions): ?int
    {
        foreach ($dimensions as $dimension) {
            if (isset($dimension['engine'])) {
                foreach ($dimension['engine'] as $engineItem) {
                    if (isset($engineItem['torque'])) {
                        $torqueData = $engineItem['torque'];
                        if (is_array($torqueData)) {
                            foreach ($torqueData as $item) {
                                if (isset($item['unit']) && $item['unit'] === 'lb.ft' && !empty($item['value'])) {
                                    return (int) $item['value'];
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract available colors from additional data
     */
    private function extractAvailableColors(array $additionalData): array
    {
        $colors = $additionalData['exterior_colors'] ?? [];
        $availableColors = [];

        foreach ($colors as $color) {
            $availableColors[] = [
                'name' => $color['generic_name'] ?? '',
                'description' => $color['description'] ?? '',
                'color_code' => $color['color_code'] ?? '',
                'hex_value' => $color['hex_value'] ?? '',
                'rgb_value' => $color['rgb_value'] ?? '',
                'color_type' => $color['color_type'] ?? '',
                'price' => $color['color_price'] ?? '',
            ];
        }

        return $availableColors;
    }

    /**
     * Extract engine size (for electric vehicles, this might be battery capacity)
     */
    private function extractEngineSize(array $specifications, array $dimensions): ?string
    {
        // For electric vehicles, try to get battery capacity
        foreach ($specifications as $spec) {
            if (isset($spec['traction_battery']['battery_capacity_kwh'])) {
                $batteryCapacity = $spec['traction_battery']['battery_capacity_kwh'];
                if (!empty($batteryCapacity)) {
                    return $batteryCapacity . ' kWh';
                }
            }
        }

        // Check dimensions for traditional engine size
        foreach ($dimensions as $dimension) {
            if (isset($dimension['engine'])) {
                foreach ($dimension['engine'] as $engineItem) {
                    if (isset($engineItem['engine_size'])) {
                        $engineSizeData = $engineItem['engine_size'];
                        if (is_array($engineSizeData)) {
                            foreach ($engineSizeData as $item) {
                                if (isset($item['unit']) && $item['unit'] === 'l' && !empty($item['value'])) {
                                    return $item['value'] . 'L';
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get brand name variations for fuzzy matching
     */
    private function getBrandVariations(string $brandName): array
    {
        $variations = [];
        $lowerName = strtolower($brandName);

        // Common brand name variations
        $brandMappings = [
            'mercedes-benz' => ['mercedes', 'benz'],
            'bmw' => ['bayerische motoren werke'],
            'land rover' => ['landrover'],
            'mini' => ['mini cooper'],
        ];

        foreach ($brandMappings as $key => $values) {
            if (strpos($lowerName, $key) !== false) {
                $variations = array_merge($variations, $values);
            }
        }

        return $variations;
    }

    /**
     * Get model name variations for fuzzy matching
     */
    private function getModelVariations(string $modelName): array
    {
        $variations = [];
        $lowerName = strtolower($modelName);

        // Common model name variations
        $modelMappings = [
            'c-class' => ['class c'],
            'e-class' => ['class e'],
            's-class' => ['class s'],
            'a-class' => ['class a'],
            'b-class' => ['class b'],
            'gl-class' => ['class gl'],
            'cls-class' => ['class cls'],
            'cl-class' => ['class cl'],
            'glk-class' => ['class glk'],
            '3 series' => ['3er', 'series 3'],
            '5 series' => ['5er', 'series 5'],
            '7 series' => ['7er', 'series 7'],
            'qashqai' => ['qashqai+2'],
        ];

        foreach ($modelMappings as $key => $values) {
            if (strpos($lowerName, $key) !== false) {
                $variations = array_merge($variations, $values);
            }
        }

        return $variations;
    }

    /**
     * Normalize model names for resilient matching
     */
    private function normalizeModelName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/\b(class|series|sedan|coupe|hatchback|convertible|suv|van|truck|sportback|fastback)\b/', '', $name);
        $name = preg_replace('/[^a-z0-9]+/', '', $name);
        return $name ?? '';
    }

    /**
     * Extract engine size from standard features
     */
    private function extractEngineSizeFromFeatures(array $standardFeatures): ?float
    {
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+\.?\d*)\s*L\s*I-?\d*\s*Engine/i', $feature, $matches)) {
                return (float) $matches[1];
            }
        }
        return null;
    }

    /**
     * Extract horsepower from standard features
     */
    private function extractHorsepowerFromFeatures(array $standardFeatures): ?int
    {
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+)\s*@\s*[\d,]+\s*rpm\s*Horsepower/i', $feature, $matches)) {
                return (int) $matches[1];
            }
        }
        return null;
    }

    /**
     * Extract torque from standard features
     */
    private function extractTorqueFromFeatures(array $standardFeatures): ?int
    {
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+)\s*@\s*[\d,]+\s*rpm\s*Torque/i', $feature, $matches)) {
                return (int) $matches[1];
            }
        }
        return null;
    }

    /**
     * Extract cylinders from standard features
     */
    private function extractCylindersFromFeatures(array $standardFeatures): ?int
    {
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+\.?\d*)\s*L\s*I-(\d+)\s*Engine/i', $feature, $matches)) {
                return (int) $matches[2];
            }
        }
        return null;
    }

    /**
     * Extract seating capacity from standard features and options
     */
    private function extractSeatingCapacityFromFeatures(array $standardFeatures, array $standardOptions = []): ?int
    {
        // Look for seating capacity in standard features
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+)\s*passenger/i', $feature, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/(\d+)\s*seat/i', $feature, $matches)) {
                return (int) $matches[1];
            }
        }

        // Look for seating capacity in standard options
        foreach ($standardOptions as $option) {
            if (preg_match('/(\d+)\s*passenger/i', $option['description'] ?? '', $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/(\d+)\s*seat/i', $option['description'] ?? '', $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract transmission gears from specifications and features
     */
    private function extractTransmissionGears(array $specifications, array $standardFeatures): ?int
    {
        // Look for transmission gears in specifications
        foreach ($specifications as $spec) {
            if (isset($spec['transmission']['number_of_speeds'])) {
                $gears = $spec['transmission']['number_of_speeds'];
                if (!empty($gears)) {
                    return (int) $gears;
                }
            }
        }

        // Look for transmission gears in standard features
        foreach ($standardFeatures as $feature) {
            if (preg_match('/(\d+)\s*speed\s*(automatic|manual|cvt)/i', $feature, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/(\d+)\s*gear/i', $feature, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract fuel type from specifications
     */
    private function extractFuelTypeFromSpecifications(array $specifications): ?string
    {
        foreach ($specifications as $spec) {
            if (isset($spec['fuel']['type'])) {
                $fuelType = $spec['fuel']['type'];
                if (!empty($fuelType)) {
                    return $fuelType;
                }
            }
        }
        return null;
    }

    /**
     * Extract cylinders from specifications
     */
    private function extractCylindersFromSpecifications(array $specifications): ?int
    {
        foreach ($specifications as $spec) {
            if (isset($spec['engine']['cylinders_configuration'])) {
                $cylinderConfig = $spec['engine']['cylinders_configuration'];
                if (!empty($cylinderConfig)) {
                    // Extract number from configurations like "I4", "V6", "V8", "I6", etc.
                    if (preg_match('/(\d+)/', $cylinderConfig, $matches)) {
                        return (int) $matches[1];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract GCC/Import information
     */
    private function extractGccImport(array $vinData, array $additionalData, array $basicData): ?string
    {
        // Try to get country of origin from various sources
        $country = $additionalData['country_of_origin'] ?? 
                  $basicData['country_of_origin'] ?? 
                  $vinData['country_of_origin'] ?? 
                  $additionalData['manufacturer_country'] ?? 
                  $basicData['manufacturer_country'] ?? 
                  $vinData['manufacturer_country'] ?? null;

        if ($country) {
            // Map to GCC/Import format
            return $this->mapCountryToGccImport($country);
        }

        // Try to determine from manufacturer
        $manufacturer = $additionalData['manufacturer'] ?? $basicData['manufacturer'] ?? $vinData['manufacturer'] ?? null;
        
        if ($manufacturer) {
            $countryMappings = [
                'Toyota' => 'Japan',
                'Honda' => 'Japan',
                'Nissan' => 'Japan',
                'Hyundai' => 'South Korea',
                'Kia' => 'South Korea',
                'BMW' => 'Germany',
                'Mercedes-Benz' => 'Germany',
                'Audi' => 'Germany',
                'Volkswagen' => 'Germany',
                'Ford' => 'USA',
                'Chevrolet' => 'USA',
                'Cadillac' => 'USA',
                'Tesla' => 'USA',
                'Ferrari' => 'Italy',
                'Lamborghini' => 'Italy',
                'Maserati' => 'Italy',
                'Volvo' => 'Sweden',
                'Saab' => 'Sweden',
            ];

            $country = $countryMappings[$manufacturer] ?? null;
            if ($country) {
                return $this->mapCountryToGccImport($country);
            }
        }

        // Try to extract from VIN pattern or plant code
        $vin = $basicData['vin'] ?? $additionalData['vin'] ?? $vinData['vin'] ?? '';
        $plant = $additionalData['plant'] ?? $basicData['plant'] ?? null;
        
        // Check VIN 11th character (plant code) - some manufacturers use specific codes for GCC
        // K = Korea, J = Japan, U = USA, W = Germany, etc.
        if (strlen($vin) >= 11) {
            $plantCode = strtoupper($vin[10]);
            $gccPlantCodes = ['K', 'U', '1', '2', '3', '4', '5']; // These might indicate GCC assembly
            
            // Note: VIN plant codes vary by manufacturer, so this is a basic check
            // You may need to adjust based on your specific requirements
        }

        // If still no determination, return null (let user specify manually)
        // Or you can return a default like 'Import' if you prefer
        return null;
    }

    /**
     * Map country to GCC/Import format
     */
    private function mapCountryToGccImport(?string $country): ?string
    {
        if (!$country) {
            return null;
        }

        // GCC countries
        $gccCountries = [
            'United Arab Emirates',
            'UAE',
            'Saudi Arabia',
            'Kuwait',
            'Qatar',
            'Bahrain',
            'Oman',
            'GCC',
        ];

        $countryLower = strtolower($country);
        foreach ($gccCountries as $gccCountry) {
            if (strpos($countryLower, strtolower($gccCountry)) !== false) {
                return 'GCC';
            }
        }

        // Everything else is Import
        return 'Import';
    }

    /**
     * Extract damaged parts information
     */
    private function extractDamagedParts(array $vinData, array $additionalData, array $basicData): ?int
    {
        // Look for damage information in various sources
        $damageInfo = $additionalData['damage'] ?? 
                     $basicData['damage'] ?? 
                     $vinData['damage'] ?? 
                     $additionalData['accident_history'] ?? 
                     $basicData['accident_history'] ?? 
                     $vinData['accident_history'] ?? null;

        if ($damageInfo) {
            if (is_array($damageInfo)) {
                // Count damaged parts if it's an array
                return count($damageInfo);
            } elseif (is_string($damageInfo)) {
                // Try to extract number from damage description
                if (preg_match('/(\d+)\s*(damaged|parts?|areas?)/i', $damageInfo, $matches)) {
                    return (int) $matches[1];
                }
                // If there's any damage mentioned, assume at least 1 part
                return 1;
            } elseif (is_numeric($damageInfo)) {
                return (int) $damageInfo;
            }
        }

        // Check for accident history
        $accidentHistory = $additionalData['accident_count'] ?? 
                          $basicData['accident_count'] ?? 
                          $vinData['accident_count'] ?? null;

        if ($accidentHistory && is_numeric($accidentHistory)) {
            return (int) $accidentHistory;
        }

        return null;
    }
}
