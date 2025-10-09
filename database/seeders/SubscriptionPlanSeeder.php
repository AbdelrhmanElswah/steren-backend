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
        $odooBaseUrl = config('services.odoo.base_url');
        $apiKey = config('services.odoo.api_key');

        try {
            $this->command->info('Fetching subscription plans from Odoo API...');

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get($odooBaseUrl . '/api/steren/available-subscription-plans');

            if (!$response->successful()) {
                $this->command->error('Failed to fetch subscription plans from Odoo API');
                $this->command->info('Seeding with default plans instead...');
                $this->seedDefaultPlans();
                return;
            }

            $data = $response->json();

            if (!isset($data['result']['success']) || !$data['result']['success']) {
                $this->command->error('Odoo API returned unsuccessful response');
                $this->command->info('Seeding with default plans instead...');
                $this->seedDefaultPlans();
                return;
            }

            $plans = $data['result']['data'] ?? [];

            if (empty($plans)) {
                $this->command->warn('No subscription plans found in Odoo API');
                $this->command->info('Seeding with default plans instead...');
                $this->seedDefaultPlans();
                return;
            }

            $this->command->info('Found ' . count($plans) . ' subscription plans from Odoo');

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

            $this->command->info('Successfully seeded ' . count($plans) . ' subscription plans from Odoo!');

        } catch (\Exception $e) {
            Log::error('Error seeding subscription plans from Odoo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->command->error('Error: ' . $e->getMessage());
            $this->command->info('Seeding with default plans instead...');
            $this->seedDefaultPlans();
        }
    }

    /**
     * Seed default subscription plans if Odoo API fails
     */
    private function seedDefaultPlans(): void
    {
        $plans = [
            [
                'odoo_id' => '1',
                'name' => 'Starter',
                'description' => 'Basic plan for individual sellers. Perfect for selling 1-5 cars.',
                'price' => 20000.00,
                'currency' => 'IQD',
                'currency_symbol' => ' ع.د',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Up to 5 car listings',
                    'Basic analytics',
                    'Email support',
                    'Standard listing features'
                ],
                'max_listings' => 8,
                'max_photos' => 8,
                'subscriber_count' => 0,
                'is_active' => true,
                'is_popular' => false,
                'is_featured' => true,
                'is_whatsapp_phone' => true,
                'is_badge' => true,
                'is_search' => true,
                'is_similar_cars' => false,
                'is_dedicated_account_rep' => false,
                'is_analytics' => false,
                'is_top_picks' => false,
                'is_priority_support' => false,
                'is_you_may_also_like' => false,
                'sort_order' => 1,
            ],
            [
                'odoo_id' => '2',
                'name' => 'Pro',
                'description' => 'Professional plan for active sellers. Great for selling 6-20 cars.',
                'price' => 40000.00,
                'currency' => 'IQD',
                'currency_symbol' => ' ع.د',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Up to 12 car listings',
                    'Advance analytics',
                    'Email and mobile support',
                    'Enhanced listing features'
                ],
                'max_listings' => 20,
                'max_photos' => 12,
                'subscriber_count' => 0,
                'is_active' => true,
                'is_popular' => true,
                'is_featured' => true,
                'is_whatsapp_phone' => true,
                'is_badge' => true,
                'is_search' => true,
                'is_similar_cars' => true,
                'is_dedicated_account_rep' => true,
                'is_analytics' => true,
                'is_top_picks' => false,
                'is_priority_support' => false,
                'is_you_may_also_like' => false,
                'sort_order' => 2,
            ],
            [
                'odoo_id' => '3',
                'name' => 'Dealer',
                'description' => 'Premium plan for dealers and high-volume sellers. Unlimited listings.',
                'price' => 52000.00,
                'currency' => 'IQD',
                'currency_symbol' => ' ع.د',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Unlimited listings',
                    'Advance analytics',
                    'Email and mobile support',
                    'Enhanced listing features'
                ],
                'max_listings' => 999,
                'max_photos' => 18,
                'subscriber_count' => 0,
                'is_active' => true,
                'is_popular' => true,
                'is_featured' => true,
                'is_whatsapp_phone' => true,
                'is_badge' => true,
                'is_search' => true,
                'is_similar_cars' => true,
                'is_dedicated_account_rep' => true,
                'is_analytics' => true,
                'is_top_picks' => true,
                'is_priority_support' => true,
                'is_you_may_also_like' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['odoo_id' => $plan['odoo_id']],
                $plan
            );
            $this->command->info('✓ Seeded default: ' . $plan['name']);
        }

        $this->command->info('Successfully seeded default subscription plans!');
    }
}

