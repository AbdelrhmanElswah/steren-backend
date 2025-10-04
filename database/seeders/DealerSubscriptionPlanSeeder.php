<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class DealerSubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for small dealerships starting out',
                'price' => 50.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Post up to 10 listings per month',
                    'Verified Dealer badge',
                    'Standard visibility in search results'
                ],
                'max_listings' => 10,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'description' => 'Best for growing dealerships',
                'price' => 100.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Post up to 50 listings per month',
                    'Verified Dealer badge',
                    'Standard visibility in search results',
                    'Access to Dealer Dashboard with views & leads analytics'
                ],
                'max_listings' => 50,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Hero',
                'description' => 'Ideal for established dealers who want maximum reach',
                'price' => 150.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Unlimited listings',
                    'Verified Dealer badge',
                    'Featured Ads placement (always on top)',
                    'Full dealer analytics & insights',
                    'Dedicated support'
                ],
                'max_listings' => null, // Unlimited
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }
    }
}
