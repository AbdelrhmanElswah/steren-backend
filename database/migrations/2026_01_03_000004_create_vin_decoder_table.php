<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vin_decoder', function (Blueprint $table) {
            $table->id();
            $table->string('vin', 17)->unique(); // VIN is always 17 characters
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('trim')->nullable();
            $table->string('body_type')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drive_type')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('exterior_color')->nullable();
            $table->string('interior_color')->nullable();
            $table->integer('doors')->nullable();
            $table->integer('cylinders')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('plant')->nullable();
            $table->json('additional_data')->nullable(); // For any additional VIN data
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vin_decoder');
    }
};
