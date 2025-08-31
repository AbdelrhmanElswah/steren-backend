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
        Schema::table('car_listings', function (Blueprint $table) {
            // Car specifications fields
            $table->string('engine_size')->nullable(); // e.g., "4.0L", "3.5L V6"
            $table->string('engine_type')->nullable(); // e.g., "V6", "Twin-Turbo"
            $table->integer('horsepower')->nullable(); // e.g., 326, 409
            $table->string('torque')->nullable(); // e.g., "650 Nm"
            $table->decimal('acceleration_0_100', 4, 1)->nullable(); // seconds
            $table->integer('top_speed')->nullable(); // km/h
            $table->integer('seating_capacity')->nullable(); // e.g., 7
            $table->decimal('mileage_efficiency', 5, 2)->nullable(); // e.g., 9.8 Km/L
            
            // Dimensions
            $table->decimal('length', 6, 2)->nullable(); // mm
            $table->decimal('width', 6, 2)->nullable(); // mm
            $table->decimal('height', 6, 2)->nullable(); // mm
            
            // Status fields
            $table->string('status')->default('active');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            
            // Additional metadata
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // Array of features
            $table->json('specifications')->nullable(); // Additional specs
            $table->string('seller_name')->nullable(); // e.g., "Erbill", "AUTO MOBILE"
            $table->string('seller_phone')->nullable();
            $table->string('seller_email')->nullable();
            
            // Timestamps for status changes
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('featured_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropColumn([
                'engine_size',
                'engine_type',
                'horsepower',
                'torque',
                'acceleration_0_100',
                'top_speed',
                'seating_capacity',
                'mileage_efficiency',
                'length',
                'width',
                'height',
                'status',
                'is_featured',
                'is_new_arrival',
                'description',
                'features',
                'specifications',
                'seller_name',
                'seller_phone',
                'seller_email',
                'sold_at',
                'featured_at',
            ]);
        });
    }
};
