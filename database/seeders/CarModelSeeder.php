<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarModel;
use App\Models\CarBrand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarModelSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Fetching car models from Odoo API...');

        try {
            $allModels = [];
            $offset = 0;
            $limit = 100;

            while (true) {
                $this->command->info("Fetching (offset: {$offset}, limit: {$limit})");

                // Correct request format for your Odoo API
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post(config('services.odoo.base_url') . '/api/models', [
                    "limit" => $limit,
                    "offset" => $offset
                ]);

                if (!$response->successful()) {
                    $this->command->error("Request failed. Status: " . $response->status());
                    Log::error('CarModelSeeder API failure', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    break;
                }

                $json = $response->json();

                // Odoo format: result.data
                if (!isset($json['result']['data']) || !is_array($json['result']['data'])) {
                    $this->command->error('Invalid API response format.');
                    Log::error('Invalid format', ['response' => $json]);
                    break;
                }

                $models = $json['result']['data'];

                // Stop if no more data
                if (empty($models)) {
                    $this->command->info("No more models returned. Pagination complete.");
                    break;
                }

                $count = count($models);
                $this->command->info("Fetched {$count} models");

                // Add to buffer
                $allModels = array_merge($allModels, $models);

                // Increase offset
                $offset += $count;

                // Stop if less than limit (last page)
                if ($count < $limit) {
                    $this->command->info("Last page detected (returned {$count} < {$limit}).");
                    break;
                }
            }

            $this->command->info("Total raw models fetched: " . count($allModels));

            // Deduplicate by odoo_id
            $uniqueModels = [];
            foreach ($allModels as $m) {
                $uniqueModels[$m['id']] = $m;
            }

            $allModels = array_values($uniqueModels);

            $this->command->info("Unique models after removing duplicates: " . count($allModels));


            // Insert / update models
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($allModels as $model) {

                // brand_id must match local CarBrand odoo_id
                $brand = CarBrand::where('odoo_id', $model['brand_id'])->first();

                if (!$brand) {
                    $this->command->warn("Brand not found (odoo_id={$model['brand_id']}) → Skipping model {$model['name']}");
                    $skipped++;
                    continue;
                }

                $carModel = CarModel::updateOrCreate(
                    ['odoo_id' => $model['id']],
                    [
                        'name' => $model['name'],
                        'car_brand_id' => $brand->id
                    ]
                );

                $carModel->wasRecentlyCreated ? $created++ : $updated++;
            }

            // Final log summary
            $this->command->info("Seeding completed:");
            $this->command->info("  - Total unique from API: " . count($allModels));
            $this->command->info("  - Created: {$created}");
            $this->command->info("  - Updated: {$updated}");
            $this->command->info("  - Skipped (brand missing): {$skipped}");

        } catch (\Exception $e) {
            $this->command->error("Exception: " . $e->getMessage());

            Log::error('CarModelSeeder exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
