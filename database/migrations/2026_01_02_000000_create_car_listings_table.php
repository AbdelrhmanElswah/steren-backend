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
        Schema::create('car_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('price', 10, 2);
            $table->integer('mileage');
            $table->string('origin_country');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_brand_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_body_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->onDelete('set null');
            $table->foreignId('car_condition_id')->nullable()->constrained('car_conditions')->nullOnDelete();
            $table->foreignId('mileage_type_id')->nullable()->constrained('mileage_types')->nullOnDelete();
            $table->foreignId('import_type_id')->nullable()->constrained('import_types')->nullOnDelete();
            $table->foreignId('fuel_type_id')->nullable()->constrained('fuel_types')->nullOnDelete();
            $table->foreignId('transmission_id')->nullable()->constrained('transmissions')->nullOnDelete();
            $table->foreignId('exterior_color_id')->nullable()->constrained('exterior_colors')->nullOnDelete();
            $table->foreignId('drive_type_id')->nullable()->constrained('drive_types')->nullOnDelete();
            $table->foreignId('warranty_status_id')->nullable()->constrained('warranty_statuses')->nullOnDelete();
            $table->foreignId('cylinder_id')->nullable()->constrained('cylinder_options')->nullOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_sponsored')->default(false);
            $table->integer('views')->default(0);
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listings');
    }
};
