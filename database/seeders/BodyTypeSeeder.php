<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BodyType;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BodyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching body types from Odoo API...');
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(config('services.odoo.base_url') . '/api/bodytypes', [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => []
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Log the response structure for debugging
                $this->command->info('API Response structure: ' . json_encode(array_keys($data)));
                if (isset($data['result'])) {
                    $this->command->info('Result structure: ' . json_encode(array_keys($data['result'])));
                    
                    // If there's an error, throw exception
                    if (isset($data['result']['error'])) {
                        $errorMessage = $data['result']['error'];
                        $errorCode = $data['result']['error_code'] ?? 'N/A';
                        $this->command->error('API Error: ' . $errorMessage);
                        $this->command->error('Error code: ' . $errorCode);
                        Log::error('BodyTypeSeeder: Odoo API returned error', [
                            'error' => $errorMessage,
                            'error_code' => $errorCode
                        ]);
                        throw new \Exception('Odoo API error: ' . $errorMessage . ' (Code: ' . $errorCode . ')');
                    }
                }
                
                // Try multiple possible response structures
                $bodyTypes = null;
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $bodyTypes = $data['result']['data'];
                } elseif (isset($data['data']) && is_array($data['data'])) {
                    $bodyTypes = $data['data'];
                } elseif (isset($data['result']) && is_array($data['result'])) {
                    $bodyTypes = $data['result'];
                }
                
                if ($bodyTypes && is_array($bodyTypes)) {
                    $this->command->info("Found " . count($bodyTypes) . " body types to seed");
                    
                    foreach ($bodyTypes as $bodyTypeData) {
                        // Check if we have required fields
                        if (!isset($bodyTypeData['id']) || !isset($bodyTypeData['name'])) {
                            $this->command->warn("Skipping invalid body type data");
                            continue;
                        }
                        
                        $bodyType = BodyType::updateOrCreate(
                            ['odoo_id' => $bodyTypeData['id']], 
                            [
                                'odoo_id' => $bodyTypeData['id'], 
                                'name' => $bodyTypeData['name'],
                            ]
                        );
                        
                        if (isset($bodyTypeData['image']) && !empty($bodyTypeData['image'])) {
                            $this->handleBodyTypeImage($bodyType, $bodyTypeData['image']);
                        }
                    }
                    
                    $this->command->info('Body types seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    $this->command->info('Full response: ' . json_encode($data));
                    Log::error('BodyTypeSeeder: Invalid response format', ['response' => $data]);
                    throw new \Exception('Invalid response format from Odoo API. Expected data array but got: ' . json_encode($data));
                }
            } else {
                $this->command->error('Failed to fetch body types from Odoo API. Status: ' . $response->status());
                Log::error('BodyTypeSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('Failed to fetch body types from Odoo API. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching body types: ' . $e->getMessage());
            Log::error('BodyTypeSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle body type image conversion from base64 to binary and storage
     */
    private function handleBodyTypeImage(BodyType $bodyType, string $base64Image): void
    {
        try {
            $bodyType->medias()->delete();

            $cleanBase64 = trim($base64Image);
            
            // Handle URL encoded base64
            if (str_starts_with($cleanBase64, '+')) {
                $cleanBase64 = urldecode($cleanBase64);
            }
            
            // Remove data URL prefix if present
            if (str_contains($cleanBase64, ',')) {
                $cleanBase64 = substr($cleanBase64, strpos($cleanBase64, ',') + 1);
            }

            // Decode base64 image
            $imageData = base64_decode($cleanBase64);
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for body type ID: {$bodyType->id}");
                return;
            }

            // Detect image type
            $imageType = 'png'; // default
            if (str_starts_with($imageData, "\xFF\xD8\xFF")) {
                $imageType = 'jpg';
            } elseif (str_starts_with($imageData, "\x89PNG")) {
                $imageType = 'png';
            } elseif (str_starts_with($imageData, "GIF8")) {
                $imageType = 'gif';
            }

            // Generate filename and path
            $filename = 'body_type_' . $bodyType->id . '_' . time() . '.' . $imageType;
            $path = 'body_types/' . $filename;

            // Store the image file
            Storage::disk('public')->put($path, $imageData);

            // Create media record
            Media::create([
                'modelable_type' => BodyType::class,
                'modelable_id' => $bodyType->id,
                'media' => $filename,
                'path_file_name' => $path,
                'file_type' => 'image/' . $imageType,
                'info' => 'icon',
            ]);

            $this->command->info("Image saved for body type: {$bodyType->name} (Type: {$imageType}, Size: " . number_format(strlen($imageData)) . " bytes)");

        } catch (\Exception $e) {
            $this->command->error("Error handling image for body type {$bodyType->id}: " . $e->getMessage());
            Log::error('BodyTypeSeeder: Image handling error', [
                'body_type_id' => $bodyType->id,
                'message' => $e->getMessage(),
                'base64_preview' => substr($base64Image, 0, 50) . '...'
            ]);
        }
    }
}
