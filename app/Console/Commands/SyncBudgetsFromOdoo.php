<?php

namespace App\Console\Commands;

use App\Models\Budget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncBudgetsFromOdoo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odoo:sync-budgets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync budgets from Odoo API to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting budget sync from Odoo...');

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

            if (!$response->successful()) {
                $this->error('Failed to fetch budgets from Odoo API');
                $this->error("Status: {$response->status()}");
                $this->error("Response: {$response->body()}");
                
                // Try to parse error response
                $errorData = $response->json();
                if ($errorData) {
                    $this->error("Error details: " . json_encode($errorData, JSON_PRETTY_PRINT));
                }
                
                Log::error('SyncBudgetsFromOdoo: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url' => config('services.odoo.base_url') . '/api/budgets',
                    'headers' => $response->headers()
                ]);
                return Command::FAILURE;
            }

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
            
            if (!$budgets || !is_array($budgets)) {
                $this->error('Invalid response format from Odoo API');
                Log::error('SyncBudgetsFromOdoo: Invalid response format', ['response' => $data]);
                return Command::FAILURE;
            }
            $this->info('Found ' . count($budgets) . ' budgets');

            $created = 0;
            $updated = 0;
            $errors = 0;

            foreach ($budgets as $budgetData) {
                try {
                    $budget = Budget::updateOrCreate(
                        ['odoo_id' => $budgetData['budget_id']],
                        [
                            'name' => $budgetData['name'],
                            'value' => $budgetData['value'],
                            'sequence' => $budgetData['sequence'],
                            'odoo_id' => $budgetData['budget_id'],
                            'is_active' => true
                        ]
                    );

                    if ($budget->wasRecentlyCreated) {
                        $created++;
                        $this->line("✓ Created: {$budget->name}");
                    } else {
                        $updated++;
                        $this->line("✓ Updated: {$budget->name}");
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->error("✗ Error processing budget: {$e->getMessage()}");
                    Log::error('SyncBudgetsFromOdoo: Budget processing error', [
                        'budget_data' => $budgetData,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->newLine();
            $this->info('=== Sync Summary ===');
            $this->info("Created: {$created}");
            $this->info("Updated: {$updated}");
            if ($errors > 0) {
                $this->error("Errors: {$errors}");
            }
            $this->info('Total: ' . ($created + $updated));

            $this->newLine();
            $this->info('Budget sync completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to sync budgets: ' . $e->getMessage());
            Log::error('SyncBudgetsFromOdoo: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}

