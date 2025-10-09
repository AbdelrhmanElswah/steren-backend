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
            // Try simple GET request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get(config('services.odoo.base_url') . '/api/budgets');

            if (!$response->successful()) {
                $this->error('Failed to fetch budgets from Odoo API');
                $this->error("Status: {$response->status()}");
                Log::error('SyncBudgetsFromOdoo: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
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

