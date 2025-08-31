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
                'name' => 'Banner',
                'value' => 'banner',
                'description' => 'Banner promotion type',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Carousel',
                'value' => 'carousel',
                'description' => 'Carousel promotion type',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Popup',
                'value' => 'popup',
                'description' => 'Popup promotion type',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($promotionTypes as $type) {
            Promotion::create($type);
        }
    }
}
