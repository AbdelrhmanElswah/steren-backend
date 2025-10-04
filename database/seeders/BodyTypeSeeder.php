<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BodyType;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
<<<<<<< Updated upstream
=======
<<<<<<< HEAD
use Illuminate\Support\Str;
=======
>>>>>>> f1e647cb0f702afed587be491f9e4c378f5d7c75
>>>>>>> Stashed changes

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
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $bodyTypes = $data['result']['data'];
                    
                    $this->command->info("Found " . count($bodyTypes) . " body types to seed");
                    
<<<<<<< Updated upstream
=======
<<<<<<< HEAD
                    foreach ($bodyTypes as $bodyType) {
                        $this->command->info("Processing body type: " . $bodyType['name']);
                        
                        // Create or update the body type
                        $bodyTypeModel = BodyType::updateOrCreate(
                            ['odoo_id' => $bodyType['id']],
                            [
                                'name' => $bodyType['name'],
                                'odoo_id' => $bodyType['id']
                            ]
                        );
                        
                        // Handle image if present and not null/empty
                        if (isset($bodyType['image']) && !empty(trim($bodyType['image'])) && $bodyType['image'] !== null) {
                            $this->handleBodyTypeImage($bodyTypeModel, $bodyType['image']);
                        } else {
                            $this->command->info("No image found for body type: " . $bodyType['name']);
=======
>>>>>>> Stashed changes
                    foreach ($bodyTypes as $bodyTypeData) {
                        $bodyType = BodyType::updateOrCreate(
                            ['odoo_id' => $bodyTypeData['id']], 
                            [
                                'odoo_id' => $bodyTypeData['id'], 
                                'name' => $bodyTypeData['name'],
                            ]
                        );

                        
                        if (isset($bodyTypeData['image']) && !empty($bodyTypeData['image'])) {
                            $this->handleBodyTypeImage($bodyType, $bodyTypeData['image']);
<<<<<<< Updated upstream
=======
>>>>>>> f1e647cb0f702afed587be491f9e4c378f5d7c75
>>>>>>> Stashed changes
                        }
                    }
                    
                    $this->command->info('Body types seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('BodyTypeSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch body types from Odoo API. Status: ' . $response->status());
                Log::error('BodyTypeSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching body types: ' . $e->getMessage());
            Log::error('BodyTypeSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
<<<<<<< Updated upstream
=======
<<<<<<< HEAD
    
    /**
     * Handle body type image conversion from base64 to binary and storage
     */
    private function handleBodyTypeImage(BodyType $bodyType, string $base64Image): void
    {
        try {
            // Remove existing media if any
            $bodyType->media()->delete();
            
            // Check if base64 string is valid and not empty
            if (empty(trim($base64Image)) || $base64Image === null) {
                $this->command->warn("Empty or null base64 image for body type: " . $bodyType->name);
                return;
            }
            
            // Decode base64 image
            $imageData = base64_decode($base64Image, true); // strict mode
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for body type: " . $bodyType->name . " (invalid base64 format)");
                return;
            }
            
            // Check if decoded data is actually an image
            if (empty($imageData)) {
                $this->command->warn("Decoded image data is empty for body type: " . $bodyType->name);
                return;
            }
            
            // Generate unique filename
            $filename = 'bodytype_' . $bodyType->id . '_' . Str::slug($bodyType->name) . '_' . time() . '.png';
            
            // Store the image file
            $stored = Storage::disk('public')->put('bodytypes/' . $filename, $imageData);
            
            if ($stored) {
                // Create media record
                $media = new Media([
                    'media' => 'bodytypes/' . $filename, // Store the file path as media reference
                    'path_file_name' => 'bodytypes/' . $filename,
                    'file_type' => 'image/png',
                    'info' => 'icon'
                ]);
                
                $bodyType->media()->save($media);
                
                $this->command->info("Image saved for body type: " . $bodyType->name . " at: bodytypes/" . $filename);
            } else {
                $this->command->warn("Failed to store image for body type: " . $bodyType->name);
            }
            
        } catch (\Exception $e) {
            $this->command->error("Error handling image for body type {$bodyType->name}: " . $e->getMessage());
            Log::error('BodyTypeSeeder: Image handling error', [
                'body_type_id' => $bodyType->id,
                'body_type_name' => $bodyType->name,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
=======
>>>>>>> Stashed changes


    private function handleBodyTypeImage(BodyType $bodyType, string $base64Image): void
    {
        try {
            $bodyType->medias()->delete();

            $cleanBase64 = trim($base64Image);
            
            
            if (str_starts_with($cleanBase64, '+')) {
                $cleanBase64 = urldecode($cleanBase64);
            }
            
            
            if (str_contains($cleanBase64, ',')) {
                $cleanBase64 = substr($cleanBase64, strpos($cleanBase64, ',') + 1);
            }

            
            $imageData = base64_decode($cleanBase64);
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for body type ID: {$bodyType->id}");
                return;
            }

            
            $imageType = 'png'; // default
            if (str_starts_with($imageData, "\xFF\xD8\xFF")) {
                $imageType = 'jpg';
            } elseif (str_starts_with($imageData, "\x89PNG")) {
                $imageType = 'png';
            } elseif (str_starts_with($imageData, "GIF8")) {
                $imageType = 'gif';
            }

            
            $filename = 'body_type_' . $bodyType->id . '_' . time() . '.' . $imageType;
            $path = 'body_types/' . $filename;

            
            Storage::disk('public')->put($path, $imageData);

            
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
<<<<<<< Updated upstream
=======
>>>>>>> f1e647cb0f702afed587be491f9e4c378f5d7c75
>>>>>>> Stashed changes
