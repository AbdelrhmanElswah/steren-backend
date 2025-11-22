<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;

class PromotionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promotionTypes = [
            [
                'title' => 'Sell Your Car Today',
                'description' => 'Get the best price for your car with our trusted network of buyers',
                'image_url' => '/images/promotions/sell-car-banner.jpg',
                'call_to_action_text' => 'Sell Now',
                'call_to_action_link' => '/sell',
                'is_active' => true,
                'display_order' => 1,
                'type' => 'banner',
            ],
            [
                'title' => 'Featured Listings',
                'description' => 'Boost your car listing visibility and get more views',
                'image_url' => '/images/promotions/featured-listings.jpg',
                'call_to_action_text' => 'Boost Listing',
                'call_to_action_link' => '/boost',
                'is_active' => true,
                'display_order' => 2,
                'type' => 'carousel',
            ],
            [
                'title' => 'Special Offer',
                'description' => 'Limited time offer for new users',
                'image_url' => '/images/promotions/special-offer.jpg',
                'call_to_action_text' => 'Get Offer',
                'call_to_action_link' => '/offer',
                'is_active' => true,
                'display_order' => 3,
                'type' => 'popup',
            ],
        ];

        foreach ($promotionTypes as $type) {
            Promotion::updateOrCreate(
                ['title' => $type['title']],
                $type
            );
        }
    }
}
