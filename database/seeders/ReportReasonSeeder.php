<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReportReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reportReasons = [
            [
                'name' => 'Inaccurate Information',
                'value' => 'inaccurate_information',
                'description' => 'The listing contains false or misleading information',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Offensive Content',
                'value' => 'offensive_content',
                'description' => 'The content is offensive or inappropriate',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Spam',
                'value' => 'spam',
                'description' => 'This appears to be spam or unwanted content',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Inappropriate Language',
                'value' => 'inappropriate_language',
                'description' => 'The listing contains inappropriate language',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Copyright Infringement',
                'value' => 'copyright_infringement',
                'description' => 'This content violates copyright laws',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Other',
                'value' => 'other',
                'description' => 'Other reasons not listed above',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        // Store these reasons in a configuration or cache for easy access
        config(['report.reasons' => $reportReasons]);
    }
}
