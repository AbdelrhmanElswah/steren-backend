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
        Schema::create('car_features', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Leather Seats", "Bluetooth", "Backup Camera"
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('icon')->nullable(); // Icon name for UI
            $table->boolean('is_standard')->default(false); // Standard vs optional feature
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index('sort_order');
        });

        // Pivot table for car listings and features
        Schema::create('car_listing_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_feature_id')->constrained()->onDelete('cascade');
            $table->boolean('is_included')->default(true); // Whether this feature is included
            $table->text('notes')->nullable(); // Additional notes about the feature
            $table->timestamps();
            
            $table->unique(['car_listing_id', 'car_feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listing_feature');
        Schema::dropIfExists('car_features');
    }
};
