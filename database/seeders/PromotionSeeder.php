<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Promotion::create([
            'title' => 'READY TO CASH IN ON YOUR CAR?',
            'description' => 'Thousands are buying—make yours the next to go!',
            'image_url' => '/images/promotions/sell-car-banner.jpg',
            'call_to_action_text' => 'Sell Now',
            'call_to_action_link' => '/sell',
            'is_active' => true,
            'display_order' => 1,
            'type' => 'banner',
        ]);

        // Add more promotional content as needed
        Promotion::create([
            'title' => 'Featured Studio Listings',
            'description' => 'Discover the best cars from trusted studios',
            'image_url' => '/images/promotions/featured-studios.jpg',
            'call_to_action_text' => 'View All',
            'call_to_action_link' => '/studios',
            'is_active' => true,
            'display_order' => 2,
            'type' => 'carousel',
        ]);
    }
}
