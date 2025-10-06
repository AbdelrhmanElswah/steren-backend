<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Starter', 'Pro', 'Hero']),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 50, 200),
            'currency' => 'USD',
            'billing_cycle' => $this->faker->randomElement(['monthly', 'yearly']),
            'features' => [
                'verified_dealer_badge',
                'analytics_access',
                'priority_support'
            ],
            'max_listings' => $this->faker->numberBetween(10, 1000),
            'is_active' => true,
            'is_popular' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}