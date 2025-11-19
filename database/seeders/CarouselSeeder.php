<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Carousel;
use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CarouselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching carousels from Odoo API...');
        
        try {
            // Use POST request with JSON body (matching CarBrandSeeder pattern)
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.48.0',
            ])->post(config('services.odoo.base_url') . '/api/carousel', [
                'limit' => 0,
                'offset' => 0
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $carousels = $data['result']['data'];
                    
                    $this->command->info("Found " . count($carousels) . " carousels to seed");
                    
                    foreach ($carousels as $carouselData) {
                        $carousel = Carousel::updateOrCreate(
                            ['odoo_id' => $carouselData['id']], 
                            [
                                'odoo_id' => $carouselData['id'], 
                                'title' => $carouselData['title'],
                                'description' => $carouselData['description'],
                                'button_text' => $carouselData['button_text'],
                                'link_url' => $carouselData['link_url'] ?? 'https://www.google.com/',
                                'start_date' => $carouselData['start_date'],
                                'end_date' => $carouselData['end_date'],
                                'is_active' => true,
                                'sort_order' => $carouselData['id'], 
                            ]
                        );

                        if (isset($carouselData['img']) && !empty($carouselData['img'])) {
                            $this->handleCarouselImage($carousel, $carouselData['img']);
                        }
                    }
                    
                    $this->command->info('Carousels seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('CarouselSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch carousels from Odoo API. Status: ' . $response->status());
                Log::error('CarouselSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching carousels: ' . $e->getMessage());
            Log::error('CarouselSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle carousel image from base64 data
     */
    private function handleCarouselImage(Carousel $carousel, string $base64Image): void
    {
        try {
            // Remove existing media for this carousel
            $carousel->medias()->delete();

            // Clean the base64 string - remove any URL encoding and whitespace
            $cleanBase64 = trim($base64Image);
            
            // If it starts with +, it's URL-encoded base64, decode it first
            if (str_starts_with($cleanBase64, '+')) {
                $cleanBase64 = urldecode($cleanBase64);
            }
            
            // Remove any data URL prefix if present (data:image/png;base64,)
            if (str_contains($cleanBase64, ',')) {
                $cleanBase64 = substr($cleanBase64, strpos($cleanBase64, ',') + 1);
            }

            // Decode base64 image
            $imageData = base64_decode($cleanBase64);
            
            if ($imageData === false) {
                $this->command->warn("Failed to decode base64 image for carousel ID: {$carousel->id}");
                return;
            }

            // Detect image type from the decoded data
            $imageType = 'png'; // default
            if (str_starts_with($imageData, "\xFF\xD8\xFF")) {
                $imageType = 'jpg';
            } elseif (str_starts_with($imageData, "\x89PNG")) {
                $imageType = 'png';
            } elseif (str_starts_with($imageData, "GIF8")) {
                $imageType = 'gif';
            }

            // Generate filename with correct extension
            $filename = 'carousel_' . $carousel->id . '_' . time() . '.' . $imageType;
            $path = 'carousels/' . $filename;

            // Store image
            Storage::disk('public')->put($path, $imageData);

            // Create media record
            Media::create([
                'modelable_type' => Carousel::class,
                'modelable_id' => $carousel->id,
                'media' => $filename,
                'path_file_name' => $path,
                'file_type' => 'image/' . $imageType,
                'info' => 'main',
            ]);

            $this->command->info("Image saved for carousel: {$carousel->title} (Type: {$imageType}, Size: " . number_format(strlen($imageData)) . " bytes)");

        } catch (\Exception $e) {
            $this->command->error("Error handling image for carousel {$carousel->id}: " . $e->getMessage());
            Log::error('CarouselSeeder: Image handling error', [
                'carousel_id' => $carousel->id,
                'message' => $e->getMessage(),
                'base64_preview' => substr($base64Image, 0, 50) . '...'
            ]);
        }
    }
}
