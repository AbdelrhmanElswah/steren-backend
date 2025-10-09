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
        Schema::create('car_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->string('specification_type'); // e.g., 'engine', 'transmission', 'performance', 'dimensions'
            $table->string('name'); // e.g., 'Cylinders', 'Drive Type', 'Engine Capacity'
            $table->string('value'); // e.g., '6', 'Four Wheel Drive', '4.0'
            $table->string('unit')->nullable(); // e.g., 'L', 'Nm', 'km/h'
            $table->text('description')->nullable(); // Additional description
            $table->boolean('is_highlighted')->default(false); // For key specs to show prominently
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['car_listing_id', 'specification_type']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_specifications');
    }
};
