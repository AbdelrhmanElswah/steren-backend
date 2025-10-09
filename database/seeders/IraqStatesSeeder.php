<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IraqStatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching Iraq states from Odoo API...');
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(config('services.odoo.base_url') . '/api/states/iraq', [
                'jsonrpc' => '2.0',
                'id' => null,
                'method' => 'call',
                'params' => []
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $states = $data['result']['data'];
                    
                    $this->command->info("Found " . count($states) . " Iraq states to seed");
                    
                    foreach ($states as $state) {
                        try {
                            // Use both name and odoo_id to prevent duplicates
                            $existing = City::where('name', $state['name'])->first();
                            
                            if ($existing) {
                                // Update existing city with odoo_id
                                $existing->update(['odoo_id' => $state['id']]);
                            } else {
                                // Create new city
                                City::create([
                                    'name' => $state['name'],
                                    'odoo_id' => $state['id']
                                ]);
                            }
                        } catch (\Exception $e) {
                            $this->command->warn("Skipped duplicate city: {$state['name']}");
                            Log::warning('IraqStatesSeeder: Duplicate city skipped', [
                                'city_name' => $state['name'],
                                'odoo_id' => $state['id']
                            ]);
                        }
                    }
                    
                    $this->command->info('Iraq states seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('IraqStatesSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch Iraq states from Odoo API. Status: ' . $response->status());
                Log::error('IraqStatesSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching Iraq states: ' . $e->getMessage());
            Log::error('IraqStatesSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
