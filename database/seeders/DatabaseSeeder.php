<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CarConditionSeeder::class,
            MileageTypeSeeder::class,
            ImportTypeSeeder::class,
            FuelTypeSeeder::class,
            TransmissionSeeder::class,
            ExteriorColorSeeder::class,
            DriveTypeSeeder::class,
            WarrantyStatusSeeder::class,
            CylinderOptionSeeder::class,
            PromotionSeeder::class,
            SafetyTipSeeder::class,
            ContactMethodSeeder::class,
            CarTrimSeeder::class,
            NewsArticleSeeder::class,
            CarFeatureSeeder::class,
            ConditionTypeSeeder::class,
            PromotionTypeSeeder::class,
            StatusTypeSeeder::class,
            ReportReasonSeeder::class,
            SearchHistorySeeder::class,
            SubscriptionPlanSeeder::class,
            PaymentMethodSeeder::class,
            CarListingAnalyticsSeeder::class,
        ]);
    }
}
