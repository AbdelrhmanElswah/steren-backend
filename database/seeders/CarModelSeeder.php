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
                        $allModels = array_merge($allModels, $models);
                        
                        // Get pagination info
                        if (isset($data['result']['meta']['pagination'])) {
                            $pagination = $data['result']['meta']['pagination'];
                            $totalCount = $pagination['total_count'] ?? count($allModels);
                            $hasNext = $pagination['has_next'] ?? false;
                            $offset = $pagination['offset'] + $pagination['count'];
                            
                            $this->command->info("Fetched " . count($models) . " models (Total so far: " . count($allModels) . " / {$totalCount})");
                        } else {
                            // If no pagination metadata, assume we got all results
                            $hasNext = false;
                            $this->command->info("Fetched " . count($models) . " models (No pagination info, assuming complete)");
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
            $this->command->info("Processing car models...");
            
            $successCount = 0;
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
                CarModel::updateOrCreate(
                    ['odoo_id' => $model['id']],
                    [
                        'name' => $model['name'],
                        'car_brand_id' => $carBrand->id,
                        'odoo_id' => $model['id']
                    ]
                );
                
                $successCount++;
            }
            
            $this->command->info("Car models seeded successfully! (Success: {$successCount}, Skipped: {$skippedCount}, Total fetched: " . count($allModels) . ")");
            
        } catch (\Exception $e) {
            $this->command->error('Error fetching car models: ' . $e->getMessage());
            Log::error('CarModelSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

