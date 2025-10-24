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
        
        // Parse style to extract trim and other details
        $trim = $this->extractTrimFromStyle($style);
        $bodyType = $this->extractBodyTypeFromStyle($style);
        $transmission = $this->extractTransmissionFromStyle($style);
        $driveType = $this->extractDriveTypeFromStyle($style);
        
        // Extract additional specifications from the nested data
        $specifications = $additionalData['specifications'] ?? [];
        $dimensions = $additionalData['dimensions'] ?? [];
        $vehicle = $additionalData['vehicle'] ?? $basicData['vehicle'] ?? [];
        $standardFeatures = $basicData['standard_features'] ?? [];
        
        // Extract other information from either structure
        $fuelType = $basicData['fuel_type'] ?? 'Gasoline'; // Default to Gasoline for non-Tesla
        $exteriorColor = $basicData['exterior_color'] ?? $vinData['exterior_color'] ?? '';
        $cylinders = $basicData['cylinders'] ?? $vinData['cylinders'] ?? null;
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
        
        // Fallback: For sedans, default to 5 passengers if not found
        if ($seatingCapacity === null && $bodyType && strtolower($bodyType) === 'sedan') {
            $seatingCapacity = 5;
        }
        
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

        return [
            // Frontend required fields (matching CarSummaryPage)
            'vinNumber' => $basicData['vin'] ?? $vinData['vin'] ?? '',
            'selectedCar' => trim($make . ' ' . $model . ' ' . $trim),
            'brandLogoUrl' => $this->getBrandLogoUrl($brandId),
            'year' => $year ? (string)$year : '',
            'selectedBodyType' => $bodyTypeName,
            'gccImport' => 'UAE', // Default value, can be modified
            'fuelType' => $mappedFuelType,
            'cylinders' => $cylinders ? (string)$cylinders : '',
            'transmission' => $mappedTransmission,
            'exteriorColor' => $exteriorColor,
            'driveType' => $mappedDriveType,
            'engineSize' => $engineSize ? (float)$engineSize : null,
            'seatingCapacity' => $seatingCapacity,
            'brandId' => $brandId ? (string)$brandId : null,
            'modelId' => $modelId ? (string)$modelId : null,
            
            // Additional useful fields
            'bodyTypeName' => $bodyTypeName,
            'availableColors' => $availableColors,
            'doors' => $doors,
            'trim' => $trim,
            'condition' => 'used', // Default to used, can be modified
            'mileage' => 0, // Default value, needs to be entered manually
            'mileageType' => 'KM', // Default to KM
            'plateStatus' => 'available', // Default value
            'warranty' => 'no', // Default value
            'hasWarranty' => false,
            'isInspected' => false,
            'isVerified' => false,
            'hasInsurance' => false,
            'originCountry' => 'UAE', // Default value
            'engineType' => $vinData['engine_type'] ?? 'Gasoline',
            'horsepower' => $horsepower,
            'torque' => $torque,
            'acceleration_0_100' => $acceleration,
            'topSpeed' => $topSpeed,
            'mileageEfficiency' => $mileageEfficiency,
            'length' => $length,
            'width' => $width,
            'height' => $height,
        ];
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
    private function extractBodyTypeFromStyle(string $style): string
    {
        if (preg_match('/(Sedan|SUV|Hatchback|Coupe|Convertible|Truck|Van)/i', $style, $matches)) {
            return $matches[1];
        }
        return 'Sedan'; // Default
    }

    /**
     * Extract transmission from style string
     */
    private function extractTransmissionFromStyle(string $style): string
    {
        if (preg_match('/(Automatic|Manual|CVT)/i', $style, $matches)) {
            return $matches[1];
        }
        return 'Automatic'; // Default
    }

    /**
     * Extract drive type from style string
     */
    private function extractDriveTypeFromStyle(string $style): string
    {
        if (preg_match('/(Rear-Wheel Drive|Front-Wheel Drive|All-Wheel Drive|Four-Wheel Drive)/i', $style, $matches)) {
            return $matches[1];
        }
        return 'Rear-Wheel Drive'; // Default
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
        
        if (!$model) {
            // Try variations for common model name patterns
            $variations = $this->getModelVariations($modelName);
            foreach ($variations as $variation) {
                $model = \App\Models\CarModel::whereRaw('LOWER(name) = ?', [strtolower($variation)])
                    ->where('car_brand_id', $brandId)
                    ->first();
                if ($model) {
                    break;
                }
            }
        }
        
        return $model ? $model->id : null;
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
     * Get brand logo URL
     */
    private function getBrandLogoUrl(?int $brandId): ?string
    {
        if (!$brandId) {
            return null;
        }
        
        $brand = \App\Models\CarBrand::with('media')->find($brandId);
        return $brand && $brand->media ? $brand->media->url : null;
    }

    /**
     * Map fuel type to system format
     */
    private function mapFuelType(string $fuelType): string
    {
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
    private function mapTransmission(string $transmission): string
    {
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
    private function mapDriveType(string $driveType): string
    {
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
        ];
        
        foreach ($modelMappings as $key => $values) {
            if (strpos($lowerName, $key) !== false) {
                $variations = array_merge($variations, $values);
            }
        }
        
        return $variations;
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
}