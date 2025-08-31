<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarFeature;

class CarFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            // Interior Features
            [
                'name' => 'Leather Seats',
                'description' => 'Premium leather upholstery for comfort and style',
                'category' => 'interior',
                'icon' => 'chair',
                'is_standard' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Heated Seats',
                'description' => 'Front and rear heated seats for cold weather comfort',
                'category' => 'interior',
                'icon' => 'thermostat',
                'is_standard' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Ventilated Seats',
                'description' => 'Air-conditioned seats for hot weather comfort',
                'category' => 'interior',
                'icon' => 'air',
                'is_standard' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Power Seats',
                'description' => 'Electrically adjustable driver and passenger seats',
                'category' => 'interior',
                'icon' => 'settings',
                'is_standard' => true,
                'sort_order' => 4,
            ],

            // Exterior Features
            [
                'name' => 'LED Headlights',
                'description' => 'Bright LED headlights for better visibility',
                'category' => 'exterior',
                'icon' => 'lightbulb',
                'is_standard' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Panoramic Sunroof',
                'description' => 'Large panoramic sunroof for natural light',
                'category' => 'exterior',
                'icon' => 'roof',
                'is_standard' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Alloy Wheels',
                'description' => 'Stylish alloy wheels for enhanced appearance',
                'category' => 'exterior',
                'icon' => 'circle',
                'is_standard' => true,
                'sort_order' => 3,
            ],

            // Safety Features
            [
                'name' => 'Backup Camera',
                'description' => 'Rear-view camera for safe parking',
                'category' => 'safety',
                'icon' => 'camera',
                'is_standard' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Blind Spot Monitoring',
                'description' => 'Advanced blind spot detection system',
                'category' => 'safety',
                'icon' => 'visibility',
                'is_standard' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Lane Departure Warning',
                'description' => 'Alerts when vehicle drifts from lane',
                'category' => 'safety',
                'icon' => 'warning',
                'is_standard' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Adaptive Cruise Control',
                'description' => 'Smart cruise control that adjusts to traffic',
                'category' => 'safety',
                'icon' => 'speed',
                'is_standard' => false,
                'sort_order' => 4,
            ],

            // Comfort & Convenience
            [
                'name' => 'Keyless Entry',
                'description' => 'Keyless entry and push-button start',
                'category' => 'comfort_convenience',
                'icon' => 'key',
                'is_standard' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Dual Zone Climate Control',
                'description' => 'Separate temperature controls for driver and passenger',
                'category' => 'comfort_convenience',
                'icon' => 'thermostat',
                'is_standard' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Power Windows',
                'description' => 'One-touch power windows for all doors',
                'category' => 'comfort_convenience',
                'icon' => 'window',
                'is_standard' => true,
                'sort_order' => 3,
            ],

            // Infotainment
            [
                'name' => 'Bluetooth Connectivity',
                'description' => 'Wireless Bluetooth audio and phone connectivity',
                'category' => 'infotainment',
                'icon' => 'bluetooth',
                'is_standard' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Apple CarPlay',
                'description' => 'Seamless iPhone integration for apps and navigation',
                'category' => 'infotainment',
                'icon' => 'phone',
                'is_standard' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Android Auto',
                'description' => 'Android smartphone integration for apps and navigation',
                'category' => 'infotainment',
                'icon' => 'android',
                'is_standard' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Premium Sound System',
                'description' => 'High-quality audio system with multiple speakers',
                'category' => 'infotainment',
                'icon' => 'music_note',
                'is_standard' => false,
                'sort_order' => 4,
            ],

            // Manufacture Warranty
            [
                'name' => '3-Year Basic Warranty',
                'description' => 'Comprehensive 3-year basic warranty coverage',
                'category' => 'manufacture_warranty',
                'icon' => 'verified',
                'is_standard' => true,
                'sort_order' => 1,
            ],
            [
                'name' => '5-Year Powertrain Warranty',
                'description' => 'Extended powertrain warranty for 5 years',
                'category' => 'manufacture_warranty',
                'icon' => 'engine',
                'is_standard' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Roadside Assistance',
                'description' => '24/7 roadside assistance for peace of mind',
                'category' => 'manufacture_warranty',
                'icon' => 'help',
                'is_standard' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($features as $feature) {
            CarFeature::create($feature);
        }
    }
}
