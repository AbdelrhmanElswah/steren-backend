<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BodyType;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
