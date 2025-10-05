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
            $this->command->info("Fetching all car brands from Odoo API...");
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.48.0',
            ])->post(config('services.odoo.base_url') . '/api/brands', [
                'limit' => 0,
                'offset' => 0
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Debug: Log the full response structure
                $this->command->info("API Response structure: " . json_encode(array_keys($data)));
                if (isset($data['result'])) {
                    $this->command->info("Result structure: " . json_encode(array_keys($data['result'])));
                    if (isset($data['result']['meta'])) {
                        $this->command->info("Meta data: " . json_encode($data['result']['meta']));
                    }
                }
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $allBrands = $data['result']['data'];
                    $this->command->info("Successfully fetched " . count($allBrands) . " car brands from Odoo API");
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('CarBrandSeeder: Invalid response format', ['response' => $data]);
                    $allBrands = [];
                }
            } else {
                $this->command->error('Failed to fetch car brands from Odoo API. Status: ' . $response->status());
                Log::error('CarBrandSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                $allBrands = [];
            }
            
            $this->command->info("Found " . count($allBrands) . " total car brands to seed");
            
            foreach ($allBrands as $brand) {
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
            
            if (empty(trim($base64Image)) || $base64Image === null) {
                $this->command->warn("Empty or null base64 image for brand: " . $carBrand->name);
                return;
            }
            
            $imageData = base64_decode($base64Image, true);
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for brand: " . $carBrand->name . " (invalid base64 format)");
                return;
            }
            
            if (empty($imageData)) {
                $this->command->warn("Decoded image data is empty for brand: " . $carBrand->name);
                return;
            }
            
            $filename = 'brand_' . $carBrand->id . '_' . Str::slug($carBrand->name) . '_' . time() . '.png';
            
            // For Laravel Cloud, we need to handle storage differently
            // Try to store the file and handle any errors gracefully
            try {
                $stored = Storage::disk('public')->put('brands/' . $filename, $imageData);
                
                if ($stored) {
                    $media = new Media([
                        'media' => 'brands/' . $filename, 
                        'path_file_name' => 'brands/' . $filename,
                        'file_type' => 'image/png',
                        'info' => 'logo'
                    ]);
                    
                    $carBrand->media()->save($media);
                    
                    $this->command->info("Image saved for brand: " . $carBrand->name . " at: brands/" . $filename);
                } else {
                    $this->command->warn("Failed to store image for brand: " . $carBrand->name);
                }
            } catch (\Exception $storageException) {
                // If storage fails, create the media record anyway with the filename
                // This allows the API to work even if the file isn't stored locally
                $this->command->warn("Storage failed for brand: " . $carBrand->name . " - " . $storageException->getMessage());
                
                $media = new Media([
                    'media' => 'brands/' . $filename, 
                    'path_file_name' => 'brands/' . $filename,
                    'file_type' => 'image/png',
                    'info' => 'logo'
                ]);
                
                $carBrand->media()->save($media);
                
                $this->command->info("Media record created for brand: " . $carBrand->name . " (file storage failed)");
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
