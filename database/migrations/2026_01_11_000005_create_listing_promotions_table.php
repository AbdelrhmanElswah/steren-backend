<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listing_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Sponsored Plan', 'Free Plan'
            $table->string('type'); // 'sponsored', 'free'
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0); // 0 for free plan
            $table->string('currency')->default('IQD');
            $table->json('features'); // Store features as JSON array
            $table->json('visibility_benefits'); // Store visibility benefits as JSON
            $table->integer('priority_order')->default(0); // For sorting (higher = more priority)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default plan for new listings
            $table->timestamps();
        });

        // Insert default promotion plans
        DB::table('listing_promotions')->insert([
            [
                'name' => 'Sponsored Plan',
                'type' => 'sponsored',
                'description' => 'Premium listing with enhanced visibility and features',
                'price' => 49000.00,
                'currency' => 'IQD',
                'features' => json_encode([
                    'Top 5 list in all Hot Buttons',
                    'Hero in Top Picks & Similar Cars',
                    'Premium Badge Highlight',
                    'Up to 5x more views',
                    'Exposure on Steren\'s social media'
                ]),
                'visibility_benefits' => json_encode([
                    'priority_placement' => true,
                    'top_picks_featured' => true,
                    'social_media_promotion' => true,
                    'premium_badge' => true,
                    'enhanced_search_ranking' => true
                ]),
                'priority_order' => 100,
                'is_active' => true,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Free Plan',
                'type' => 'free',
                'description' => 'Basic listing with standard visibility',
                'price' => 0.00,
                'currency' => 'IQD',
                'features' => json_encode([
                    'Free VIN decoder',
                    'New arrival badge 1 day carousel',
                    'Get thousands of views',
                    'Shows in similar cars'
                ]),
                'visibility_benefits' => json_encode([
                    'free_vin_decoder' => true,
                    'new_arrival_badge' => true,
                    'standard_visibility' => true,
                    'similar_cars_showing' => true
                ]),
                'priority_order' => 0,
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_promotions');
    }
};
