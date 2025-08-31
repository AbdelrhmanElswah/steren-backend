<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarListingAnalytics;
use App\Models\CarListing;

class CarListingAnalyticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $carListings = CarListing::limit(10)->get();

        foreach ($carListings as $listing) {
            // Generate random analytics data for the last 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = now()->subDays($i);
                
                // Views (most common)
                if (rand(1, 100) <= 80) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'views',
                        'metric_value' => rand(1, 50),
                        'date' => $date->toDateString(),
                    ]);
                }

                // Shares (less common)
                if (rand(1, 100) <= 20) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'shares',
                        'metric_value' => rand(1, 5),
                        'date' => $date->toDateString(),
                    ]);
                }

                // Call taps (medium frequency)
                if (rand(1, 100) <= 40) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'call_taps',
                        'metric_value' => rand(1, 10),
                        'date' => $date->toDateString(),
                    ]);
                }

                // WhatsApp clicks (medium frequency)
                if (rand(1, 100) <= 35) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'whatsapp_clicks',
                        'metric_value' => rand(1, 8),
                        'date' => $date->toDateString(),
                    ]);
                }

                // SMS (rare)
                if (rand(1, 100) <= 5) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'sms',
                        'metric_value' => rand(1, 2),
                        'date' => $date->toDateString(),
                    ]);
                }

                // Email (rare)
                if (rand(1, 100) <= 3) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'email',
                        'metric_value' => rand(1, 2),
                        'date' => $date->toDateString(),
                    ]);
                }

                // Watchlist saves (medium frequency)
                if (rand(1, 100) <= 25) {
                    CarListingAnalytics::create([
                        'car_listing_id' => $listing->id,
                        'metric_type' => 'watchlist_saves',
                        'metric_value' => rand(1, 3),
                        'date' => $date->toDateString(),
                    ]);
                }
            }
        }
    }
}
