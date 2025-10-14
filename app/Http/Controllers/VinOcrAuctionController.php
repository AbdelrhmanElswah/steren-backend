<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VinOcrAuctionController extends Controller
{
    use ApiResponse;

    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.vehicledatabases.api_key');
        $this->baseUrl = 'https://api.vehicledatabases.com';
        $this->timeout = config('services.vehicledatabases.timeout', 30);
    }

    /**
     * Extract VIN from image using OCR and get auction data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ocrAndAuction(Request $request): JsonResponse
    {
        try {
            // Validate the uploaded file
            $request->validate([
                'file' => 'required|file|mimes:png,jpg,jpeg|max:10240' // 10MB max
            ]);

            $file = $request->file('file');
            
            // Step 1: Extract VIN using OCR
            $vinResult = $this->extractVinFromImage($file);
            
            if (!$vinResult['success']) {
                return $this->errorResponse(
                    'Unable to extract VIN from image: ' . $vinResult['message'],
                    400
                );
            }

            $vin = $vinResult['data']['vin'];

            // Step 2: Get auction data for the extracted VIN
            $auctionResult = $this->getAuctionData($vin);

            if (!$auctionResult['success']) {
                return $this->errorResponse(
                    'VIN extracted successfully but no auction data found: ' . $auctionResult['message'],
                    404,
                    [
                        'vin' => $vin,
                        'auction_data' => null
                    ]
                );
            }

            // Combine results
            $responseData = [
                'vin' => $vin,
                'auction_data' => $auctionResult['data']
            ];

            return $this->successResponse(
                $responseData,
                'VIN extracted and auction data retrieved successfully'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Invalid file: ' . implode(', ', $e->errors()['file']),
                400
            );
        } catch (\Exception $e) {
            Log::error('VIN OCR and Auction API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'An error occurred while processing the request',
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
    protected function extractVinFromImage($file): array
    {
        try {
            // Store file temporarily
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $response = Http::timeout($this->timeout)
                ->attach('file', fopen($fullPath, 'r'), $file->getClientOriginalName())
                ->post($this->baseUrl . '/vin-ocr');

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
     * Get auction data for a VIN
     *
     * @param string $vin
     * @return array
     */
    protected function getAuctionData(string $vin): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl . '/auction/' . $vin);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success' && isset($data['data'])) {
                    return [
                        'success' => true,
                        'data' => $this->formatAuctionData($data['data'])
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'No auction data found for VIN: ' . $vin
            ];

        } catch (\Exception $e) {
            Log::error('Auction data retrieval failed', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Auction service unavailable'
            ];
        }
    }

    /**
     * Format auction data into structured response
     *
     * @param array $rawData
     * @return array
     */
    protected function formatAuctionData(array $rawData): array
    {
        return [
            'vehicle_info' => [
                'make' => $rawData['make'] ?? null,
                'model' => $rawData['model'] ?? null,
                'year' => $rawData['year'] ?? null,
                'trim' => $rawData['trim'] ?? null,
                'body_style' => $rawData['body_style'] ?? null,
                'engine' => $rawData['engine'] ?? null,
                'transmission' => $rawData['transmission'] ?? null,
                'drive_type' => $rawData['drive_type'] ?? null,
                'fuel_type' => $rawData['fuel_type'] ?? null,
                'exterior_color' => $rawData['exterior_color'] ?? null,
                'interior_color' => $rawData['interior_color'] ?? null,
            ],
            'auction_details' => [
                'auction_date' => $rawData['auction_date'] ?? null,
                'auction_location' => $rawData['auction_location'] ?? null,
                'sale_price' => $rawData['sale_price'] ?? null,
                'current_bid' => $rawData['current_bid'] ?? null,
                'auction_status' => $rawData['auction_status'] ?? null,
                'seller_info' => [
                    'seller_name' => $rawData['seller_name'] ?? null,
                    'seller_type' => $rawData['seller_type'] ?? null,
                ]
            ],
            'vehicle_condition' => [
                'odometer_reading' => $rawData['odometer_reading'] ?? null,
                'condition_grade' => $rawData['condition_grade'] ?? null,
                'damage_assessment' => [
                    'primary_damage' => $rawData['primary_damage'] ?? null,
                    'secondary_damage' => $rawData['secondary_damage'] ?? null,
                    'damage_severity' => $rawData['damage_severity'] ?? null,
                ],
                'accident_history' => $rawData['accident_history'] ?? [],
            ],
            'auction_images' => $rawData['auction_images'] ?? [],
            'additional_info' => [
                'title_status' => $rawData['title_status'] ?? null,
                'keys_available' => $rawData['keys_available'] ?? null,
                'runs_drives' => $rawData['runs_drives'] ?? null,
                'airbags_deployed' => $rawData['airbags_deployed'] ?? null,
                'hail_damage' => $rawData['hail_damage'] ?? null,
                'flood_damage' => $rawData['flood_damage'] ?? null,
            ]
        ];
    }

    /**
     * Extract VIN from image only (OCR endpoint)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ocrOnly(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:png,jpg,jpeg|max:10240'
            ]);

            $file = $request->file('file');
            $vinResult = $this->extractVinFromImage($file);

            if (!$vinResult['success']) {
                return $this->errorResponse(
                    'Unable to extract VIN from image: ' . $vinResult['message'],
                    400
                );
            }

            return $this->successResponse(
                $vinResult['data'],
                'VIN extracted successfully'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Invalid file: ' . implode(', ', $e->errors()['file']),
                400
            );
        } catch (\Exception $e) {
            Log::error('VIN OCR error', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while extracting VIN',
                500
            );
        }
    }

    /**
     * Get auction data by VIN only
     *
     * @param string $vin
     * @return JsonResponse
     */
    public function auctionOnly(string $vin): JsonResponse
    {
        try {
            $auctionResult = $this->getAuctionData($vin);

            if (!$auctionResult['success']) {
                return $this->errorResponse(
                    'No auction data found for VIN: ' . $vin,
                    404,
                    [
                        'vin' => $vin,
                        'auction_data' => null
                    ]
                );
            }

            return $this->successResponse(
                [
                    'vin' => $vin,
                    'auction_data' => $auctionResult['data']
                ],
                'Auction data retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Auction data error', [
                'vin' => $vin,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'An error occurred while retrieving auction data',
                500
            );
        }
    }
}
