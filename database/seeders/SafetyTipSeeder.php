<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SafetyTip;

class SafetyTipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $safetyTips = [
            [
                'title' => 'Meet in a Safe Location',
                'content' => 'Always meet in a public place, preferably during daylight hours. Consider meeting at a police station parking lot or a busy shopping center.',
                'icon' => 'location',
                'category' => 'meeting',
                'display_order' => 1,
            ],
            [
                'title' => 'Verify Vehicle Documents',
                'content' => 'Always verify the vehicle registration, insurance, and maintenance records. Check that the VIN on the documents matches the vehicle.',
                'icon' => 'document',
                'category' => 'inspection',
                'display_order' => 2,
            ],
            [
                'title' => 'Get a Professional Inspection',
                'content' => 'Before making a purchase, have the vehicle inspected by a qualified mechanic. This can reveal hidden issues and save you money.',
                'icon' => 'wrench',
                'category' => 'inspection',
                'display_order' => 3,
            ],
            [
                'title' => 'Use Secure Payment Methods',
                'content' => 'Avoid cash transactions for large amounts. Use bank transfers or escrow services for secure payments.',
                'icon' => 'payment',
                'category' => 'payment',
                'display_order' => 4,
            ],
            [
                'title' => 'Trust Your Instincts',
                'content' => 'If something seems too good to be true, it probably is. Walk away from deals that make you uncomfortable.',
                'icon' => 'warning',
                'category' => 'general',
                'display_order' => 5,
            ],
        ];

        foreach ($safetyTips as $tip) {
            SafetyTip::updateOrCreate(
                ['title' => $tip['title']],
                $tip
            );
        }
    }
}
