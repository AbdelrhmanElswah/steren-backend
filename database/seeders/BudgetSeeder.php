<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Budget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching budgets from Odoo API...');
        
        try {
            // Use POST request with JSON body (matching CarBrandSeeder pattern)
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.48.0',
            ])->post(config('services.odoo.base_url') . '/api/budgets', [
                'limit' => 0,
                'offset' => 0
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                $budgets = null;
                
                // Check for simple data format first (as in your example)
                if (isset($data['data']) && is_array($data['data'])) {
                    $budgets = $data['data'];
                }
                // Then check for JSON-RPC result format
                elseif (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $budgets = $data['result']['data'];
                }
                
                if ($budgets && is_array($budgets)) {
                    $this->command->info("Found " . count($budgets) . " budgets to seed");
                    
                    $successCount = 0;
                    $skippedCount = 0;
                    
                    foreach ($budgets as $budget) {
                        try {
                            // Create or update the budget
                            Budget::updateOrCreate(
                                ['odoo_id' => $budget['budget_id']],
                                [
                                    'name' => $budget['name'],
                                    'value' => $budget['value'],
                                    'sequence' => $budget['sequence'],
                                    'odoo_id' => $budget['budget_id'],
                                    'is_active' => true
                                ]
                            );
                            
                            $successCount++;
                        } catch (\Exception $e) {
                            $this->command->warn("Failed to seed budget: {$budget['name']}. Error: {$e->getMessage()}");
                            $skippedCount++;
                            Log::error('BudgetSeeder: Failed to seed individual budget', [
                                'budget' => $budget,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    $this->command->info("Budgets seeded successfully! (Success: {$successCount}, Skipped: {$skippedCount})");
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('BudgetSeeder: Invalid response format', ['response' => $data]);
                    throw new \Exception('Invalid response format from Odoo API. Expected data array but got: ' . json_encode($data));
                }
            } else {
                $this->command->error('Failed to fetch budgets from Odoo API. Status: ' . $response->status());
                Log::error('BudgetSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('Failed to fetch budgets from Odoo API. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching budgets: ' . $e->getMessage());
            Log::error('BudgetSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

