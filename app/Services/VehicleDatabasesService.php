<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\VehicleDatabasesException;
use App\Models\VinDecoder;

class VehicleDatabasesService
{
    protected ?string $apiUrl;
    protected ?string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.vehicledatabases.api_url');
        $this->apiKey = config('services.vehicledatabases.api_key');
        $this->timeout = config('services.vehicledatabases.timeout', 30);

        // Validate configuration
        if (empty($this->apiKey)) {
            Log::warning('VehicleDatabases API key is not configured. Set VEHICLEDATABASES_API_KEY in your .env file.');
        }
    }

    /**
     * Decode VIN using Vehicle Databases API
     *
     * @param string $vin The VIN to decode
     * @param bool $cache Whether to cache the result
     * @return array Decoded VIN data
     * @throws VehicleDatabasesException
     */
    public function decodeVin(string $vin, bool $cache = true, bool $raw = false): array
    {
        // Validate VIN format
        if (!$this->isValidVin($vin)) {
            throw new VehicleDatabasesException('Invalid VIN format. VIN must be 17 characters long and contain only alphanumeric characters (excluding I, O, Q).', 400);
        }

        if ($raw) {
            $cache = false;
        }

        // Check cache first if enabled
        if ($cache) {
            $cachedResult = $this->getCachedResult($vin);
            if ($cachedResult) {
                Log::info('VIN decode result retrieved from cache', ['vin' => $vin]);
                return $cachedResult;
            }
        }

        // Check if service is configured
        if (!$this->isConfigured()) {
            throw new VehicleDatabasesException(
                'VehicleDatabases service is not configured. Please set VEHICLEDATABASES_API_KEY in your .env file.',
                500
            );
        }

        try {
            // Always use v2 endpoint for both normal and raw calls
            $url = str_replace('{vin}', $vin, $this->apiUrl);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-AuthKey' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $responseData = $response->json();

                // v2 API returns: { "status": "success", "data": {...} }
                // Extract the actual data if it's nested
                $data = $responseData['data'] ?? $responseData;

                Log::info('VIN decoded successfully', [
                    'vin' => $vin,
                    'status_code' => $response->status(),
                ]);

                if (!$raw) {
                    if ($cache) {
                        // Cache the full response data for reference
                        $this->cacheResult($vin, $responseData);
                    }

                    return [
                        'success' => true,
                        'data' => $data,
                        'status_code' => $response->status(),
                    ];
                }

                // For raw, return the full response
                return $responseData;
            }

            // Handle API errors
            $responseBody = $response->json();
            $errorMessage = $this->extractErrorMessage($responseBody, $response->status());

            Log::error('VehicleDatabases API error', [
                'vin' => $vin,
                'status' => $response->status(),
                'response' => $responseBody,
            ]);

            throw new VehicleDatabasesException(
                $errorMessage,
                $response->status()
            );

        } catch (VehicleDatabasesException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('VehicleDatabases service exception', [
                'vin' => $vin,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new VehicleDatabasesException(
                'Failed to decode VIN: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Decode VIN and save to database
     *
     * @param string $vin The VIN to decode
     * @param bool $updateExisting Whether to update existing records
     * @return VinDecoder The saved VinDecoder model
     * @throws VehicleDatabasesException
     */
    public function decodeAndSaveVin(string $vin, bool $updateExisting = true): VinDecoder
    {
        $result = $this->decodeVin($vin);

        if (!$result['success']) {
            throw new VehicleDatabasesException('Failed to decode VIN', 500);
        }

        $data = $result['data'];

        // Check if VIN already exists
        $existingVin = VinDecoder::byVin($vin)->first();

        if ($existingVin && !$updateExisting) {
            Log::info('VIN already exists in database, skipping update', ['vin' => $vin]);
            return $existingVin;
        }

        // Prepare data for database
        $vinData = $this->prepareVinData($vin, $data);

        if ($existingVin) {
            $existingVin->update($vinData);
            Log::info('VIN data updated in database', ['vin' => $vin]);
            return $existingVin;
        }

        $vinDecoder = VinDecoder::create($vinData);
        Log::info('VIN data saved to database', ['vin' => $vin]);

        return $vinDecoder;
    }

    /**
     * Get cached VIN decode result
     *
     * @param string $vin
     * @return array|null
     */
    protected function getCachedResult(string $vin): ?array
    {
        $vinDecoder = VinDecoder::byVin($vin)->first();

        if (!$vinDecoder) {
            return null;
        }

        // Check if cache is still valid (e.g., not older than 24 hours)
        $cacheExpiry = config('services.vehicledatabases.cache_expiry', 24);
        if ($vinDecoder->last_updated && $vinDecoder->last_updated->diffInHours(now()) > $cacheExpiry) {
            return null;
        }

        return [
            'success' => true,
            'data' => [
                'vin' => $vinDecoder->vin,
                'make' => $vinDecoder->make,
                'model' => $vinDecoder->model,
                'year' => $vinDecoder->year,
                'trim' => $vinDecoder->trim,
                'body_type' => $vinDecoder->body_type,
                'engine_type' => $vinDecoder->engine_type,
                'transmission' => $vinDecoder->transmission,
                'drive_type' => $vinDecoder->drive_type,
                'fuel_type' => $vinDecoder->fuel_type,
                'exterior_color' => $vinDecoder->exterior_color,
                'interior_color' => $vinDecoder->interior_color,
                'doors' => $vinDecoder->doors,
                'cylinders' => $vinDecoder->cylinders,
                'manufacturer' => $vinDecoder->manufacturer,
                'plant' => $vinDecoder->plant,
                'additional_data' => $vinDecoder->additional_data,
            ],
            'status_code' => 200,
            'cached' => true,
        ];
    }

    /**
     * Cache VIN decode result
     *
     * @param string $vin
     * @param array $data
     * @return void
     */
    protected function cacheResult(string $vin, array $data): void
    {
        try {
            $vinData = $this->prepareVinData($vin, $data);
            $vinData['last_updated'] = now();

            VinDecoder::updateOrCreate(
                ['vin' => $vin],
                $vinData
            );
        } catch (\Exception $e) {
            Log::warning('Failed to cache VIN result', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare VIN data for database storage
     *
     * @param string $vin
     * @param array $apiData
     * @return array
     */
    protected function prepareVinData(string $vin, array $apiData): array
    {
        // Handle v2 response structure: data might be nested in 'data' key or direct
        $data = $apiData['data'] ?? $apiData;

        // Map API response to database fields
        // v2 endpoint structure: make, model, year, trim are directly in data
        // vehicle.body_type, vehicle.doors are in vehicle object
        // transmission.type is in transmission object
        // specifications[9].fuel.type for fuel type
        // specifications[9].engine.cylinders_configuration for cylinders

        $vehicle = $data['vehicle'] ?? [];
        $transmission = $data['transmission'] ?? [];
        $specifications = $data['specifications'] ?? [];
        $firstSpec = $specifications[9] ?? [];

        return [
            'vin' => strtoupper($vin),
            'make' => $data['make'] ?? $apiData['make'] ?? $apiData['Make'] ?? null,
            'model' => $data['model'] ?? $apiData['model'] ?? $apiData['Model'] ?? null,
            'year' => $data['year'] ?? $apiData['year'] ?? $apiData['Year'] ?? null,
            'trim' => $data['trim'] ?? $apiData['trim'] ?? $apiData['Trim'] ?? null,
            'body_type' => $vehicle['body_type'] ?? $data['body_type'] ?? $apiData['body_type'] ?? $apiData['BodyType'] ?? null,
            'engine_type' => $firstSpec['engine']['type'] ?? $data['engine_type'] ?? $apiData['engine_type'] ?? $apiData['EngineType'] ?? null,
            'transmission' => $transmission['type'] ?? $data['transmission'] ?? (is_string($data['transmission']) ? $data['transmission'] : null) ?? $apiData['transmission'] ?? $apiData['Transmission'] ?? null,
            'drive_type' => $firstSpec['engine']['drivetype'] ?? $data['drive_type'] ?? $apiData['drive_type'] ?? $apiData['DriveType'] ?? null,
            'fuel_type' => $firstSpec['fuel']['type'] ?? $data['fuel_type'] ?? $apiData['fuel_type'] ?? $apiData['FuelType'] ?? null,
            'exterior_color' => $data['exterior_color'] ?? $apiData['exterior_color'] ?? $apiData['ExteriorColor'] ?? null,
            'interior_color' => $data['interior_color'] ?? $apiData['interior_color'] ?? $apiData['InteriorColor'] ?? null,
            'doors' => $vehicle['doors'] ?? $data['doors'] ?? $apiData['doors'] ?? $apiData['Doors'] ?? null,
            'cylinders' => $this->extractCylindersFromConfig($firstSpec['engine']['cylinders_configuration'] ?? null) ?? $data['cylinders'] ?? $apiData['cylinders'] ?? $apiData['Cylinders'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? $apiData['manufacturer'] ?? $apiData['Manufacturer'] ?? null,
            'plant' => $data['plant'] ?? $apiData['plant'] ?? $apiData['Plant'] ?? null,
            'additional_data' => $apiData, // Store full response for reference
        ];
    }

    /**
     * Extract cylinder count from cylinders_configuration string (e.g., "I-4" -> 4)
     */
    protected function extractCylindersFromConfig(?string $config): ?int
    {
        if (!$config) {
            return null;
        }

        if (preg_match('/(\d+)/', $config, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Validate VIN format
     *
     * @param string $vin
     * @return bool
     */
    protected function isValidVin(string $vin): bool
    {
        // Remove spaces and convert to uppercase
        $vin = strtoupper(str_replace(' ', '', $vin));

        // Check length
        if (strlen($vin) !== 17) {
            return false;
        }

        // Check for invalid characters (I, O, Q)
        if (preg_match('/[IOQ]/', $vin)) {
            return false;
        }

        // Check if all characters are alphanumeric
        return ctype_alnum($vin);
    }

    /**
     * Extract error message from API response
     *
     * @param array $responseBody
     * @param int $statusCode
     * @return string
     */
    protected function extractErrorMessage(array $responseBody, int $statusCode): string
    {
        $errorMessage = 'Failed to decode VIN';

        if (isset($responseBody['message'])) {
            $errorMessage = is_array($responseBody['message'])
                ? json_encode($responseBody['message'])
                : $responseBody['message'];
        } elseif (isset($responseBody['error'])) {
            $errorMessage = is_array($responseBody['error'])
                ? json_encode($responseBody['error'])
                : $responseBody['error'];
        } elseif (isset($responseBody['errors'])) {
            $errorMessage = is_array($responseBody['errors'])
                ? json_encode($responseBody['errors'])
                : $responseBody['errors'];
        }

        return $errorMessage . ' (HTTP ' . $statusCode . ')';
    }

    /**
     * Check if service is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }

    /**
     * Get service status
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'api_url' => $this->apiUrl,
            'timeout' => $this->timeout,
        ];
    }
}
