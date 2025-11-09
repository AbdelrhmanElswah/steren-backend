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

            $rawResult = null;
            try {
                $rawResult = $this->vehicleDatabasesService->decodeVin($vin, false, true);
            } catch (\Throwable $rawException) {
                Log::warning('VIN raw decode fallback failed', [
                    'vin' => $vin,
                    'error' => $rawException->getMessage(),
                ]);
            }

            $vinData = $result['data'];

            // Filter and map the data for car listing creation
            $filteredData = $this->filterVinDataForListing($vinData, $rawResult);

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
     * Decode VIN and return the raw payload from Vehicle Databases API
     */
    public function decodeRaw(VinDecodeRequest $request): JsonResponse
    {
        try {
            $vin = $request->validated()['vin'];
            $cache = $request->validated()['cache'] ?? true;

            $result = $this->vehicleDatabasesService->decodeVin($vin, $cache, true);

            if (!$result) {
                return $this->errorResponse('Failed to decode VIN', 400);
            }

            return $this->successResponse($result, 'VIN decoded successfully (raw data)');
        } catch (VehicleDatabasesException $e) {
            Log::error('VIN raw decode failed', [
                'vin' => $request->input('vin'),
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN raw decode', [
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
     * Extract VIN via OCR and return the raw decode payload.
     */
    public function ocrAndDecodeRaw(Request $request): JsonResponse
    {
        try {
            Log::info('OCR Raw Request', [
                'hasFile_file' => $request->hasFile('file'),
                'hasFile_image' => $request->hasFile('image'),
                'hasFile_upload' => $request->hasFile('upload'),
                'allFiles' => array_keys($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'all_input_keys' => array_keys($request->all()),
            ]);

            $fileField = null;
            if ($request->hasFile('file')) {
                $fileField = 'file';
            } elseif ($request->hasFile('image')) {
                $fileField = 'image';
            } elseif ($request->hasFile('upload')) {
                $fileField = 'upload';
            }

            if (!$fileField && ($allFiles = $request->allFiles())) {
                $fileField = array_key_first($allFiles);
            }

            if (!$fileField) {
                $contentType = $request->header('Content-Type', 'not set');
                $isJson = str_contains($contentType, 'application/json');

                $errorMessage = $isJson
                    ? 'Invalid request format. The request was sent as JSON but files must be uploaded using multipart/form-data. In Postman: Body tab → form-data → add key "file" (type File) and select your image.'
                    : 'File is required. Please upload an image file (PNG, JPG, JPEG) using the "file", "image", or "upload" field in multipart/form-data format.';

                Log::warning('OCR raw file upload failed', [
                    'hasFile_file' => $request->hasFile('file'),
                    'hasFile_image' => $request->hasFile('image'),
                    'hasFile_upload' => $request->hasFile('upload'),
                    'allFiles' => array_keys($request->allFiles()),
                    'content_type' => $contentType,
                    'is_json' => $isJson,
                ]);

                return $this->errorResponse($errorMessage, 400);
            }

            $request->validate([
                $fileField => 'required|file|mimes:png,jpg,jpeg|max:10240'
            ]);

            $file = $request->file($fileField);

            $vinResult = $this->extractVinFromImage($file);

            if (!$vinResult['success']) {
                return $this->errorResponse(
                    'Unable to extract VIN from image: ' . $vinResult['message'],
                    400
                );
            }

            $vin = $vinResult['data']['vin'];

            $result = $this->vehicleDatabasesService->decodeVin($vin, true, true);

            if (!$result) {
                return $this->errorResponse('Failed to decode VIN', 400);
            }

            return $this->successResponse([
                'vin' => $vin,
                'raw' => $result,
            ], 'VIN extracted and decoded successfully (raw data)');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $fieldErrors) {
                $errors = array_merge($errors, $fieldErrors);
            }

            $errorMessage = !empty($errors)
                ? 'Invalid file: ' . implode(', ', $errors)
                : 'File validation failed. Please ensure you upload a valid image file (PNG, JPG, JPEG) with a maximum size of 10MB.';

            return $this->errorResponse($errorMessage, 400);
        } catch (VehicleDatabasesException $e) {
            Log::error('VIN OCR and raw decode failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            return $this->errorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during VIN OCR and raw decode', [
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
     * Extract VIN from image using OCR and decode it for listing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ocrAndDecodeForListing(Request $request): JsonResponse
    {
        try {
            // Debug logging
            Log::info('OCR Request', [
                'hasFile_file' => $request->hasFile('file'),
                'hasFile_image' => $request->hasFile('image'),
                'hasFile_upload' => $request->hasFile('upload'),
                'allFiles' => array_keys($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'all_input_keys' => array_keys($request->all()),
            ]);

            // Determine which field has the file
            $fileField = null;
            if ($request->hasFile('file')) {
                $fileField = 'file';
            } elseif ($request->hasFile('image')) {
                $fileField = 'image';
            } elseif ($request->hasFile('upload')) {
                $fileField = 'upload';
            }

            // Also check allFiles() as a fallback in case hasFile() isn't working
            $allFiles = $request->allFiles();
            if (!$fileField && !empty($allFiles)) {
                $fileField = array_key_first($allFiles);
            }

            // Validate the request - require at least one file field
            if (!$fileField) {
                $contentType = $request->header('Content-Type', 'not set');
                $isJson = str_contains($contentType, 'application/json');
                
                $errorMessage = $isJson 
                    ? 'Invalid request format. The request was sent as JSON but files must be uploaded using multipart/form-data. In Postman: 1) Select Body tab, 2) Choose "form-data" (not raw/JSON), 3) Add key "file" with type "File", 4) Select your image file.'
                    : 'File is required. Please upload an image file (PNG, JPG, JPEG) using the "file", "image", or "upload" field in multipart/form-data format.';
                
                Log::warning('OCR file upload failed', [
                    'hasFile_file' => $request->hasFile('file'),
                    'hasFile_image' => $request->hasFile('image'),
                    'hasFile_upload' => $request->hasFile('upload'),
                    'allFiles' => array_keys($request->allFiles()),
                    'content_type' => $contentType,
                    'is_json' => $isJson,
                ]);
                
                return $this->errorResponse($errorMessage, 400);
            }

            // Validate the specific file field that contains the upload
            $request->validate([
                $fileField => 'required|file|mimes:png,jpg,jpeg|max:10240'
            ]);

            // Get the validated file
            $file = $request->file($fileField);

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

            $rawResult = null;
            try {
                $rawResult = $this->vehicleDatabasesService->decodeVin($vin, true, true);
            } catch (\Throwable $rawException) {
                Log::warning('VIN OCR raw decode fallback failed', [
                    'vin' => $vin,
                    'error' => $rawException->getMessage(),
                ]);
            }

            // Step 3: Filter and map the data for car listing creation
            $filteredData = $this->filterVinDataForListing($vinData, $rawResult);

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
            // Collect all validation errors
            $errors = [];
            foreach ($e->errors() as $field => $fieldErrors) {
                $errors = array_merge($errors, $fieldErrors);
            }
            
            $errorMessage = !empty($errors) 
                ? 'Invalid file: ' . implode(', ', $errors)
                : 'File validation failed. Please ensure you upload a valid image file (PNG, JPG, JPEG) with a maximum size of 10MB.';
            
            return $this->errorResponse($errorMessage, 400);
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

            // Use vehicledatabases OCR endpoint (matches VinOcrAuctionController approach)
            $baseUrl = 'https://api.vehicledatabases.com';
            $ocrEndpoint = config('services.vehicledatabases.ocr_endpoint');
            
            // If ocr_endpoint is configured, use it; otherwise construct from baseUrl
            $endpoint = $ocrEndpoint ?: ($baseUrl . '/vin-ocr');
            $apiKey = config('services.vehicledatabases.api_key');
            $timeout = config('services.vehicledatabases.timeout', 30);
            
            Log::info('Calling OCR service', [
                'endpoint' => $endpoint,
                'has_api_key' => !empty($apiKey),
                'timeout' => $timeout,
            ]);
            
            // Build HTTP request with timeout and authentication (matches VehicleDatabasesService)
            $headers = [
                'Accept' => 'application/json',
            ];
            
            // Add API key header if available (using x-AuthKey like VehicleDatabasesService)
            if ($apiKey) {
                $headers['x-AuthKey'] = $apiKey;
            }
            
            // Make the request with file attachment and headers
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->attach('file', fopen($fullPath, 'r'), $file->getClientOriginalName())
                ->post($endpoint);

            // Clean up temp file
            Storage::disk('local')->delete($tempPath);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']['vin'])) {
                    return [
                        'success' => true,
                        'data' => [
                            'vin' => $data['data']['vin']
                        ]
                    ];
                }
                
                Log::warning('OCR service returned unexpected response', [
                    'status_code' => $response->status(),
                    'response' => $response->json(),
                ]);
            } else {
                Log::warning('OCR service request failed', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Failed to extract VIN from image. Status: ' . $response->status()
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('VIN OCR extraction failed - connection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'OCR service unavailable: Cannot connect to OCR service. Please check your network connection and API endpoint configuration.'
            ];
        } catch (\Exception $e) {
            Log::error('VIN OCR extraction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'OCR service unavailable: ' . $e->getMessage()
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
    private function filterVinDataForListing(array $vinData, ?array $rawResult = null): array
    {
        $rawData = null;
        if (is_array($rawResult)) {
            if (isset($rawResult['data']) && is_array($rawResult['data'])) {
                $rawData = $rawResult['data'];
            } elseif (isset($rawResult['result']) && is_array($rawResult['result'])) {
                $rawData = $rawResult['result'];
            } else {
                $rawData = $rawResult;
            }
        }

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

        // Extract transmission gears from data
        $transmissionGears = $this->extractTransmissionGears($specifications, $additionalData, $basicData, $rawData);
        
        // Extract GCC/Import information
        $gccImportData = $this->extractGccImport($vinData, $additionalData, $basicData);
        
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
        if (!empty($gccImportData['category'])) {
            $response['general']['gcc_import'] = $gccImportData['category'];
        }

        if (!empty($gccImportData['country'])) {
            $response['general']['gcc_import_country'] = $gccImportData['country'];
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
            $normalizedBaseEngineSize = $this->normalizeEngineSize($engineSize, true);
            $response['engine']['engine_size'] = $normalizedBaseEngineSize ?? $engineSize;
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

        if ($rawData) {
            $trimOverride = $this->extractRawValue($rawData, [
                ['specification', 'trim'],
                ['vehicle', 'trim'],
                ['vehicle_info', 'trim'],
                ['general', 'trim'],
                ['trim'],
            ], ['trim']);

            if ($trimOverride) {
                $response['general']['trim'] = (string) $trimOverride;
            }

            $bodyTypeOverride = $this->extractRawValue($rawData, [
                ['specification', 'body_type'],
                ['vehicle', 'body_type'],
                ['vehicle', 'body_style'],
                ['general', 'body_type'],
                ['general', 'body_style'],
                ['body', 'type'],
                ['body', 'style'],
                ['body_type'],
            ], ['body_type', 'body_style']);

            if ($bodyTypeOverride) {
                $response['general']['body_type'] = (string) $bodyTypeOverride;
            }

            $engineSizeOverride = $this->extractRawValue($rawData, [
                ['engine', 'size'],
                ['engine', 'engine_size'],
                ['engine', 'displacement'],
                ['specification', 'engine_size'],
                ['specification', 'engine', 'size'],
                ['technical', 'engine', 'size'],
            ], ['engine_size', 'engine_displacement', 'displacement']);

            if ($engineSizeOverride) {
                $normalizedEngineSize = $this->normalizeEngineSize($engineSizeOverride, true);
                if ($normalizedEngineSize) {
                    $response['engine']['engine_size'] = $normalizedEngineSize;
                }
            }

            $driveTypeOverride = $this->extractRawValue($rawData, [
                ['drivetrain', 'drive_type'],
                ['drivetrain', 'type'],
                ['drivetrain', 'drivetrain_type'],
                ['vehicle', 'drive_type'],
                ['specification', 'drive_type'],
                ['drive_type'],
            ], ['drive_type', 'drivetrain']);

            if ($driveTypeOverride) {
                $mappedDriveType = $this->mapDriveType((string) $driveTypeOverride);
                if ($mappedDriveType) {
                    $response['engine']['drive_type'] = $mappedDriveType;
                }
            }

            $gccRaw = $this->extractRawValue($rawData, [
                ['market', 'region'],
                ['market', 'name'],
                ['market', 'specification'],
                ['regional', 'specification'],
                ['general', 'regional_specification'],
                ['specification', 'regional_specification'],
                ['regional_specification'],
            ], ['regional_specification', 'market', 'market_region', 'market_name', 'region']);

            if ($gccRaw) {
                $gccString = strtoupper(trim((string) $gccRaw));
                $validGccValues = ['GCC', 'AMERICAN', 'CANADIAN', 'EUROPEAN', 'JAPANESE', 'KOREAN', 'CHINESE', 'OTHERS'];

                if (in_array($gccString, $validGccValues, true)) {
                    $response['general']['gcc_import'] = $gccString;
                    $response['general']['gcc_import_country'] = (string) $gccRaw;
                } elseif (str_contains($gccString, 'GCC')) {
                    $response['general']['gcc_import'] = 'GCC';
                    $response['general']['gcc_import_country'] = (string) $gccRaw;
                } else {
                    $mappedGcc = $this->mapCountryToGccImport($gccRaw);
                    if ($mappedGcc) {
                        $response['general']['gcc_import'] = $mappedGcc;
                        $response['general']['gcc_import_country'] = (string) $gccRaw;
                    }
                }
            }

            if (!isset($response['general']['gcc_import']) || $response['general']['gcc_import'] === null) {
                $manufacturerCountry = $this->extractRawValue($rawData, [
                    ['manufacturer', 'country'],
                    ['manufacturer', 'country_of_origin'],
                    ['manufacturer', 'origin'],
                    ['specification', 'manufacturer', 'country'],
                    ['general', 'manufacturer_country'],
                ], ['manufacturer_country', 'country_of_origin', 'manufacturer_origin']);

                if ($manufacturerCountry) {
                    $mappedFromManufacturer = $this->mapCountryToGccImport($manufacturerCountry);
                    if ($mappedFromManufacturer) {
                        $response['general']['gcc_import'] = $mappedFromManufacturer;
                        $response['general']['gcc_import_country'] = (string) $manufacturerCountry;
                    }
                }
            }
        }
        
        // Additional fields for backward compatibility and extended data
        // Add brand_id and model_id for internal validation (not exposed in final response)
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

    private function extractRawValue(?array $rawData, array $paths = [], array $fallbackKeys = [])
    {
        if (!$rawData) {
            return null;
        }

        foreach ($paths as $path) {
            $value = $this->getValueByCaseInsensitivePath($rawData, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        if (!empty($fallbackKeys)) {
            return $this->findFirstMatchInArray($rawData, $fallbackKeys);
        }

        return null;
    }

    private function getValueByCaseInsensitivePath(array $data, array $segments)
    {
        $current = $data;
        foreach ($segments as $segment) {
            if (!is_array($current)) {
                return null;
            }

            $found = false;
            foreach ($current as $key => $value) {
                if (strtolower((string) $key) === strtolower((string) $segment)) {
                    $current = $value;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return null;
            }
        }

        return $current;
    }

    private function findFirstMatchInArray(array $data, array $keys)
    {
        $keys = array_map(fn ($k) => strtolower((string) $k), $keys);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));

        foreach ($iterator as $key => $value) {
            if (in_array(strtolower((string) $key), $keys, true) && $value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeEngineSize($value, bool $stripUnit = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $numericValue = (float) $value;
            if ($numericValue > 10) {
                $liters = round($numericValue / 1000, 1);
                return $stripUnit ? (float) $liters : $liters . 'L';
            }

            $liters = rtrim(rtrim(number_format($numericValue, 1, '.', ''), '0'), '.');
            return $stripUnit ? (float) $liters : $liters . 'L';
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (preg_match('/(\d+(?:\.\d+)?)(?:\s*(?:l|litre|liter))/i', $trimmed, $matches)) {
                return $stripUnit ? (float) $matches[1] : $matches[1] . 'L';
            }

            if (preg_match('/(\d+)\s*cc/i', $trimmed, $matches)) {
                $cc = (float) $matches[1];
                $liters = round($cc / 1000, 1);
                return $stripUnit ? (float) $liters : $liters . 'L';
            }

            return $stripUnit ? (float) $trimmed : $trimmed;
        }

        return null;
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

        // Clean the brand name (trim whitespace)
        $brandName = trim($brandName);
        
        if (empty($brandName)) {
            return null;
        }

        $normalizedBrandName = strtolower($brandName);

        // Get all brands and match in PHP (handles any whitespace issues)
        $brands = \App\Models\CarBrand::get(['id', 'name']);
        
        foreach ($brands as $brand) {
            $normalizedDbName = strtolower(trim($brand->name));
            if ($normalizedDbName === $normalizedBrandName) {
                return $brand->id;
            }
        }

        // If not found, try variations
        $variations = $this->getBrandVariations($brandName);
        foreach ($variations as $variation) {
            $variation = trim($variation);
            foreach ($brands as $brand) {
                $normalizedDbName = strtolower(trim($brand->name));
                if ($normalizedDbName === strtolower($variation)) {
                    return $brand->id;
                }
            }
        }

        return null;
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
     * Extract transmission gears from data
     */
    private function extractTransmissionGears(array $specifications, array $additionalData = [], array $basicData = [], ?array $rawData = null): ?int
    {
        if ($rawData) {
            $rawGears = $this->extractRawValue($rawData, [
                ['transmission', 'number_of_speeds'],
                ['vehicle', 'transmission', 'number_of_speeds'],
                ['specification', 'transmission', 'number_of_speeds'],
                ['specification', 'transmission', 'gears'],
                ['drivetrain', 'number_of_gears'],
            ], ['number_of_speeds', 'transmission_speeds', 'transmission_gears', 'gears']);

            if ($rawGears !== null && $rawGears !== '') {
                return (int) $rawGears;
            }
        }

        // First, check transmission object directly in additional_data.data.transmission
        $transmission = $additionalData['transmission'] ?? $basicData['transmission'] ?? null;
        if ($transmission && isset($transmission['number_of_speeds'])) {
            $gears = $transmission['number_of_speeds'];
            if (!empty($gears)) {
                return (int) $gears;
            }
        }

        // Look for transmission gears in specifications array
        foreach ($specifications as $spec) {
            if (isset($spec['transmission']['number_of_speeds'])) {
                $gears = $spec['transmission']['number_of_speeds'];
                if (!empty($gears)) {
                    return (int) $gears;
                }
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
    private function extractGccImport(array $vinData, array $additionalData, array $basicData): array
    {
        $result = [
            'category' => null,
            'country' => null,
        ];

        // Try to get country of origin from various sources
        $country = $additionalData['country_of_origin'] ?? 
                  $basicData['country_of_origin'] ?? 
                  $vinData['country_of_origin'] ?? 
                  $additionalData['manufacturer_country'] ?? 
                  $basicData['manufacturer_country'] ?? 
                  $vinData['manufacturer_country'] ?? null;

        if ($country) {
            $result['country'] = $country;
            // Map to GCC/Import format
            $mapped = $this->mapCountryToGccImport($country);
            if ($mapped) {
                $result['category'] = $mapped;
            }
        }

        // Try to determine from manufacturer or make (API provides 'make' not 'manufacturer')
        $manufacturer = $additionalData['manufacturer'] ?? $basicData['manufacturer'] ?? $vinData['manufacturer'] ?? null;
        $make = $additionalData['make'] ?? $basicData['make'] ?? $vinData['make'] ?? null;
        
        // Use make if manufacturer is not available
        $brandName = $manufacturer ?? $make;
        
        if ($brandName) {
            // Expanded brand to country mappings
            // Note: For combined names like "Chevrolet / GMC", check each part
            $countryMappings = [
                // Japanese brands
                'Toyota' => 'Japan',
                'Honda' => 'Japan',
                'Nissan' => 'Japan',
                'Mazda' => 'Japan',
                'Subaru' => 'Japan',
                'Mitsubishi' => 'Japan',
                'Suzuki' => 'Japan',
                'Lexus' => 'Japan',
                'Acura' => 'Japan',
                'Infiniti' => 'Japan',
                // Korean brands
                'Hyundai' => 'South Korea',
                'Kia' => 'South Korea',
                'Genesis' => 'South Korea',
                // European brands
                'BMW' => 'Germany',
                'Mercedes-Benz' => 'Germany',
                'Mercedes' => 'Germany',
                'Audi' => 'Germany',
                'Volkswagen' => 'Germany',
                'Porsche' => 'Germany',
                'Ferrari' => 'Italy',
                'Lamborghini' => 'Italy',
                'Maserati' => 'Italy',
                'Fiat' => 'Italy',
                'Alfa Romeo' => 'Italy',
                'Volvo' => 'Sweden',
                'Saab' => 'Sweden',
                'Renault' => 'France',
                'Peugeot' => 'France',
                'Citroën' => 'France',
                'Citroen' => 'France',
                'Jaguar' => 'UK',
                'Land Rover' => 'UK',
                'Mini' => 'UK',
                'Bentley' => 'UK',
                'Rolls-Royce' => 'UK',
                // American brands
                'Ford' => 'USA',
                'Chevrolet' => 'USA',
                'GMC' => 'USA',
                'Cadillac' => 'USA',
                'Buick' => 'USA',
                'Chrysler' => 'USA',
                'Dodge' => 'USA',
                'Jeep' => 'USA',
                'Ram' => 'USA',
                'Tesla' => 'USA',
                'Lincoln' => 'USA',
                // Canadian brands (if any in your system)
                // Chinese brands
                'BYD' => 'China',
                'Chery' => 'China',
                'Geely' => 'China',
                'Foton' => 'China',
                'Great Wall' => 'China',
                'MG' => 'China',
            ];

            // Try exact match first
            $country = $countryMappings[$brandName] ?? null;
            
            // If not found, try partial match (for combined names like "Chevrolet / GMC")
            if (!$country) {
                foreach ($countryMappings as $mappedBrand => $mappedCountry) {
                    // Check if the brand name contains the mapped brand (case-insensitive)
                    if (stripos($brandName, $mappedBrand) !== false) {
                        $country = $mappedCountry;
                        break;
                    }
                }
            }
            
            if ($country) {
                $result['country'] = $country;
                $mapped = $this->mapCountryToGccImport($country);
                if ($mapped) {
                    $result['category'] = $mapped;
                }
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
        return $result;
    }

    /**
     * Map country to GCC/Import format
     * Returns: GCC, American, Canadian, European, Japanese, Korean, Chinese, or Others
     */
    private function mapCountryToGccImport(?string $country): ?string
    {
        if (!$country) {
            return null;
        }

        $countryLower = strtolower($country);

        // GCC countries
        $gccCountries = [
            'united arab emirates',
            'uae',
            'saudi arabia',
            'kuwait',
            'qatar',
            'bahrain',
            'oman',
            'gcc',
        ];
        foreach ($gccCountries as $gccCountry) {
            if (strpos($countryLower, $gccCountry) !== false) {
                return 'GCC';
            }
        }

        // American countries (USA)
        $americanCountries = [
            'usa',
            'united states',
            'united states of america',
            'us',
        ];
        foreach ($americanCountries as $americanCountry) {
            if (strpos($countryLower, $americanCountry) !== false) {
                return 'American';
            }
        }

        // Canadian
        if (strpos($countryLower, 'canada') !== false || strpos($countryLower, 'canadian') !== false) {
            return 'Canadian';
        }

        // Japanese
        if (strpos($countryLower, 'japan') !== false || strpos($countryLower, 'japanese') !== false) {
            return 'Japanese';
        }

        // Korean
        if (strpos($countryLower, 'korea') !== false || strpos($countryLower, 'korean') !== false || strpos($countryLower, 'south korea') !== false) {
            return 'Korean';
        }

        // Chinese
        if (strpos($countryLower, 'china') !== false || strpos($countryLower, 'chinese') !== false || strpos($countryLower, 'prc') !== false) {
            return 'Chinese';
        }

        // European countries
        $europeanCountries = [
            'germany',
            'german',
            'france',
            'french',
            'italy',
            'italian',
            'uk',
            'united kingdom',
            'britain',
            'british',
            'england',
            'sweden',
            'swedish',
            'spain',
            'spanish',
            'netherlands',
            'dutch',
            'belgium',
            'belgian',
            'austria',
            'austrian',
            'switzerland',
            'swiss',
            'poland',
            'polish',
            'portugal',
            'portuguese',
            'greece',
            'greek',
            'czech',
            'romania',
            'hungary',
            'denmark',
            'norway',
            'finland',
        ];
        foreach ($europeanCountries as $europeanCountry) {
            if (strpos($countryLower, $europeanCountry) !== false) {
                return 'European';
            }
        }

        // Everything else
        return 'Others';
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
