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
        Schema::create('car_trims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained('car_models')->onDelete('cascade');
            $table->string('name'); // e.g., "4.0L EXR", "3.5T GXR", "Black Edition"
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2); // Starting price for this trim
            $table->string('engine_size')->nullable(); // e.g., "4.0L", "3.5T"
            $table->string('engine_type')->nullable(); // e.g., "V6", "Twin-Turbo"
            $table->integer('horsepower')->nullable();
            $table->string('torque')->nullable(); // e.g., "650 Nm"
            $table->string('transmission')->nullable(); // e.g., "10-Speed Automatic"
            $table->integer('cylinders')->nullable();
            $table->string('drive_type')->nullable(); // e.g., "Four-Wheel Drive"
            $table->decimal('acceleration_0_100', 4, 1)->nullable(); // seconds
            $table->integer('top_speed')->nullable(); // km/h
            $table->integer('seating_capacity')->nullable();
            $table->json('features')->nullable(); // Array of features for this trim
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['car_model_id', 'is_active']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_trims');
    }
};
