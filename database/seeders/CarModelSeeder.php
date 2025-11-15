<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CarModel;
use App\Models\CarBrand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching car models from Odoo API...');
        
        try {
            $allModels = [];
            $offset = 0;
            $limit = 100; // Fetch 100 records per page
            $hasNext = true;
            $totalCount = 0;
            
            while ($hasNext) {
                $this->command->info("Fetching page (offset: {$offset}, limit: {$limit})...");
                
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post(config('services.odoo.base_url') . '/api/models', [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'method' => 'call',
                    'params' => [
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                        $models = $data['result']['data'];
                        
                        // If no models returned, stop pagination
                        if (empty($models)) {
                            $this->command->info("No more models returned. Stopping pagination.");
                            break;
                        }
                        
                        // Get pagination info first to know the total count
                        if (isset($data['result']['meta']['pagination'])) {
                            $pagination = $data['result']['meta']['pagination'];
                            $totalCount = $pagination['total_count'] ?? $totalCount;
                            $hasNext = $pagination['has_next'] ?? false;
                            
                            // Only take the models we need (in case we exceed total_count)
                            $currentCount = count($allModels);
                            $neededCount = $totalCount - $currentCount;
                            
                            if ($neededCount <= 0) {
                                // Already have enough, don't add any more
                                $this->command->info("Already have {$totalCount} models. Stopping pagination.");
                                break;
                            }
                            
                            // Only take as many models as we need
                            $modelsToAdd = array_slice($models, 0, $neededCount);
                            $allModels = array_merge($allModels, $modelsToAdd);
                            $modelsCount = count($modelsToAdd);
                            
                            // Calculate next offset ourselves (current offset + number of models actually added)
                            $offset = $offset + count($models);
                            
                            // If we took fewer models than returned, we've reached the total
                            if (count($modelsToAdd) < count($models)) {
                                $this->command->info("Reached total count ({$totalCount}). Stopping pagination.");
                                $hasNext = false;
                            }
                            
                            // Safety check: stop if has_next is false (no more pages)
                            if (!$hasNext) {
                                $this->command->info("No more pages available (has_next: false). Stopping pagination.");
                            }
                            
                            $this->command->info("Fetched {$modelsCount} models (Total so far: " . count($allModels) . " / {$totalCount}, has_next: " . ($hasNext ? 'true' : 'false') . ")");
                        } else {
                            // If no pagination metadata, just add all models and stop after first page
                            $allModels = array_merge($allModels, $models);
                            $this->command->info("Fetched " . count($models) . " models (No pagination info, stopping)");
                            $hasNext = false;
                        }
                    } else {
                        $this->command->error('Invalid response format from Odoo API');
                        Log::error('CarModelSeeder: Invalid response format', ['response' => $data]);
                        break;
                    }
                } else {
                    $this->command->error('Failed to fetch car models from Odoo API. Status: ' . $response->status());
                    Log::error('CarModelSeeder: API request failed', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    break;
                }
            }
            
            $this->command->info("Total models fetched: " . count($allModels));
            
            // Remove duplicates based on odoo_id (keep the last occurrence)
            $uniqueModels = [];
            $duplicateCount = 0;
            foreach ($allModels as $model) {
                $odooId = $model['id'];
                if (isset($uniqueModels[$odooId])) {
                    $duplicateCount++;
                }
                $uniqueModels[$odooId] = $model;
            }
            $allModels = array_values($uniqueModels);
            
            if ($duplicateCount > 0) {
                $this->command->warn("Found {$duplicateCount} duplicate odoo_ids in API response. Removed duplicates.");
                $this->command->info("Unique models to process: " . count($allModels));
            }
            
            $this->command->info("Processing car models...");
            
            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            
            foreach ($allModels as $model) {
                // Find the local car brand by its odoo_id
                $carBrand = CarBrand::where('odoo_id', $model['brand_id'])->first();
                
                if (!$carBrand) {
                    $this->command->warn("Brand with odoo_id {$model['brand_id']} not found for model: {$model['name']}. Skipping...");
                    $skippedCount++;
                    continue;
                }
                
                // Create or update the car model
                $carModel = CarModel::updateOrCreate(
                    ['odoo_id' => $model['id']],
                    [
                        'name' => $model['name'],
                        'car_brand_id' => $carBrand->id,
                        'odoo_id' => $model['id']
                    ]
                );
                
                // Check if it was newly created or updated
                if ($carModel->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }
            
            $this->command->info("Car models seeded successfully!");
            $this->command->info("  - Total fetched from API: " . count($allModels));
            $this->command->info("  - Newly created: {$createdCount}");
            $this->command->info("  - Updated: {$updatedCount}");
            $this->command->info("  - Skipped (brand not found): {$skippedCount}");
            
        } catch (\Exception $e) {
            $this->command->error('Error fetching car models: ' . $e->getMessage());
            Log::error('CarModelSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

