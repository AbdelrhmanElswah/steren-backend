<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching subscription plans from Odoo API...');
        
        try {
            // Use POST request with JSON body (matching CarBrandSeeder pattern)
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.48.0',
            ])->post(config('services.odoo.base_url') . '/api/steren/subscription-plans', [
                'limit' => 0,
                'offset' => 0
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $plans = $data['result']['data'];
                    
                    $this->command->info("Found " . count($plans) . " subscription plans to seed");
                    
                    foreach ($plans as $index => $plan) {
                        $planData = [
                            'odoo_id' => (string) $plan['plan_id'],
                            'name' => $plan['name'],
                            'description' => $plan['description'] ?? null,
                            'price' => $plan['price'],
                            'currency' => $plan['currency'] ?? 'IQD',
                            'currency_symbol' => $plan['currency_symbol'] ?? null,
                            'billing_cycle' => $plan['billing_cycle'] ?? 'yearly',
                            'features' => $plan['features'] ?? [],
                            'max_listings' => $plan['max_listings'] ?? null,
                            'max_photos' => $plan['max_photos'] ?? null,
                            'subscriber_count' => $plan['subscriber_count'] ?? 0,
                            'is_active' => true,
                            'is_popular' => $plan['is_popular'] ?? false,
                            'is_featured' => $plan['is_featured'] ?? false,
                            'is_whatsapp_phone' => $plan['is_whatsapp_phone'] ?? false,
                            'is_badge' => $plan['is_badge'] ?? false,
                            'is_search' => $plan['is_search'] ?? false,
                            'is_similar_cars' => $plan['is_similar_cars'] ?? false,
                            'is_dedicated_account_rep' => $plan['is_dedicated_account_rep'] ?? false,
                            'is_analytics' => $plan['is_analytics'] ?? false,
                            'is_top_picks' => $plan['is_top_picks'] ?? false,
                            'is_priority_support' => $plan['is_priority_support'] ?? false,
                            'is_you_may_also_like' => $plan['is_you_may_also_like'] ?? false,
                            'sort_order' => $index + 1,
                        ];

                        SubscriptionPlan::updateOrCreate(
                            ['odoo_id' => $planData['odoo_id']],
                            $planData
                        );

                        $this->command->info('✓ Seeded: ' . $plan['name']);
                    }
                    
                    $this->command->info('Subscription plans seeded successfully!');
                } else {
                    $this->command->error('Invalid response format from Odoo API');
                    Log::error('SubscriptionPlanSeeder: Invalid response format', ['response' => $data]);
                }
            } else {
                $this->command->error('Failed to fetch subscription plans from Odoo API. Status: ' . $response->status());
                Log::error('SubscriptionPlanSeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $this->command->error('Error fetching subscription plans: ' . $e->getMessage());
            Log::error('SubscriptionPlanSeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
}

