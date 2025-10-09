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
            // Try simple GET request first (as shown in your example)
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get(config('services.odoo.base_url') . '/api/budgets');
            
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
                    $this->command->warn('Using fallback budgets...');
                    $this->seedDefaultBudgets();
                    Log::error('BudgetSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch budgets from Odoo API. Status: ' . $response->status());
                $this->command->warn('Using fallback budgets...');
                $this->seedDefaultBudgets();
                Log::error('BudgetSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching budgets: ' . $e->getMessage());
            $this->command->warn('Using fallback budgets...');
            $this->seedDefaultBudgets();
            Log::error('BudgetSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Seed default budgets as fallback
     */
    private function seedDefaultBudgets(): void
    {
        $budgets = [
            ['odoo_id' => '1', 'name' => 'Under 10K', 'value' => 10000.00, 'sequence' => 1],
            ['odoo_id' => '2', 'name' => '10K - 20K', 'value' => 20000.00, 'sequence' => 2],
            ['odoo_id' => '3', 'name' => '20K - 30K', 'value' => 30000.00, 'sequence' => 3],
            ['odoo_id' => '4', 'name' => '30K - 50K', 'value' => 50000.00, 'sequence' => 4],
            ['odoo_id' => '5', 'name' => '50K - 75K', 'value' => 75000.00, 'sequence' => 5],
            ['odoo_id' => '6', 'name' => '75K - 100K', 'value' => 100000.00, 'sequence' => 6],
            ['odoo_id' => '7', 'name' => 'Over 100K', 'value' => 999999999.00, 'sequence' => 7],
        ];

        $count = 0;
        foreach ($budgets as $budget) {
            Budget::updateOrCreate(
                ['odoo_id' => $budget['odoo_id']],
                array_merge($budget, ['is_active' => true])
            );
            $this->command->line("✓ Seeded default: {$budget['name']}");
            $count++;
        }

        $this->command->info("Successfully seeded {$count} default budgets!");
    }
}

