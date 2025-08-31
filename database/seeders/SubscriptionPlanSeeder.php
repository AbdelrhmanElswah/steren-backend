<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'description' => 'Perfect for individual sellers',
                'price' => 9.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Up to 5 car listings',
                    'Basic analytics',
                    'Email support',
                    'Standard listing features',
                ],
                'max_listings' => 5,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'description' => 'Ideal for active car dealers',
                'price' => 29.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Up to 25 car listings',
                    'Advanced analytics',
                    'Priority support',
                    'Featured listings',
                    'Price drop notifications',
                    'Watch list management',
                ],
                'max_listings' => 25,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'description' => 'For professional car dealerships',
                'price' => 99.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Unlimited car listings',
                    'Premium analytics',
                    '24/7 phone support',
                    'Featured listings',
                    'Price drop notifications',
                    'Watch list management',
                    'Advanced search filters',
                    'Bulk listing tools',
                    'Custom branding',
                ],
                'max_listings' => null, // Unlimited
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Basic Yearly',
                'description' => 'Basic plan with yearly billing (Save 20%)',
                'price' => 95.88, // 9.99 * 12 * 0.8
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Up to 5 car listings',
                    'Basic analytics',
                    'Email support',
                    'Standard listing features',
                    '20% discount on yearly billing',
                ],
                'max_listings' => 5,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Premium Yearly',
                'description' => 'Premium plan with yearly billing (Save 20%)',
                'price' => 287.88, // 29.99 * 12 * 0.8
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Up to 25 car listings',
                    'Advanced analytics',
                    'Priority support',
                    'Featured listings',
                    'Price drop notifications',
                    'Watch list management',
                    '20% discount on yearly billing',
                ],
                'max_listings' => 25,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
