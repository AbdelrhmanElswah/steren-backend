<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CarBrand;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching car brands from Odoo API...');
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(config('services.odoo.base_url') . '/api/brands', [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => []
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $brands = $data['result']['data'];
                    
                    $this->command->info("Found " . count($brands) . " car brands to seed");
                    
                    foreach ($brands as $brand) {
                        $this->command->info("Processing brand: " . $brand['name']);
                        
                        // Create or update the car brand
                        $carBrand = CarBrand::updateOrCreate(
                            ['odoo_id' => $brand['id']],
                            [
                                'name' => $brand['name'],
                                'odoo_id' => $brand['id']
                            ]
                        );
                        
                        // Handle image if present and not null/empty
                        if (isset($brand['image']) && !empty(trim($brand['image'])) && $brand['image'] !== null) {
                            $this->handleBrandImage($carBrand, $brand['image']);
                        } else {
                            $this->command->info("No image found for brand: " . $brand['name']);
                        }
                    }
                    
                    $this->command->info('Car brands seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('CarBrandSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch car brands from Odoo API. Status: ' . $response->status());
                Log::error('CarBrandSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching car brands: ' . $e->getMessage());
            Log::error('CarBrandSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Handle brand image conversion from base64 to binary and storage
     */
    private function handleBrandImage(CarBrand $carBrand, string $base64Image): void
    {
        try {
            // Remove existing media if any
            $carBrand->media()->delete();
            
            // Check if base64 string is valid and not empty
            if (empty(trim($base64Image)) || $base64Image === null) {
                $this->command->warn("Empty or null base64 image for brand: " . $carBrand->name);
                return;
            }
            
            // Decode base64 image
            $imageData = base64_decode($base64Image, true); // strict mode
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for brand: " . $carBrand->name . " (invalid base64 format)");
                return;
            }
            
            // Check if decoded data is actually an image
            if (empty($imageData)) {
                $this->command->warn("Decoded image data is empty for brand: " . $carBrand->name);
                return;
            }
            
            // Generate unique filename
            $filename = 'brand_' . $carBrand->id . '_' . Str::slug($carBrand->name) . '_' . time() . '.png';
            
            // Store the image file
            $stored = Storage::disk('public')->put('brands/' . $filename, $imageData);
            
            if ($stored) {
                // Create media record
                $media = new Media([
                    'media' => 'brands/' . $filename, // Store the file path as media reference
                    'path_file_name' => 'brands/' . $filename,
                    'file_type' => 'image/png',
                    'info' => 'logo'
                ]);
                
                $carBrand->media()->save($media);
                
                $this->command->info("Image saved for brand: " . $carBrand->name . " at: brands/" . $filename);
            } else {
                $this->command->warn("Failed to store image for brand: " . $carBrand->name);
            }
            
        } catch (\Exception $e) {
            $this->command->error("Error handling image for brand {$carBrand->name}: " . $e->getMessage());
            Log::error('CarBrandSeeder: Image handling error', [
                'brand_id' => $carBrand->id,
                'brand_name' => $carBrand->name,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
