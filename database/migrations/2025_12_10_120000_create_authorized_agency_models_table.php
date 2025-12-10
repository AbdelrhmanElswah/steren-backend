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
        Schema::create('authorized_agency_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorized_agency_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_model_id')->constrained('car_models')->onDelete('cascade');
            $table->string('gcc_import')->nullable();
            $table->string('engine_size')->nullable();
            $table->string('forced_induction', 50)->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('cylinders')->nullable();
            $table->string('transmission')->nullable();
            $table->integer('seating_capacity')->nullable();
            $table->string('drive_type')->nullable();
            $table->decimal('price_start_from', 12, 2)->nullable();
            $table->integer('horse_power')->nullable();
            $table->decimal('length_from', 8, 2)->nullable();
            $table->decimal('length_to', 8, 2)->nullable();
            $table->decimal('width_from', 8, 2)->nullable();
            $table->decimal('width_to', 8, 2)->nullable();
            $table->decimal('height_from', 8, 2)->nullable();
            $table->decimal('height_to', 8, 2)->nullable();
            $table->string('torque')->nullable();
            $table->decimal('acceleration', 5, 2)->nullable();
            $table->integer('top_speed')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorized_agency_models');
    }
};


