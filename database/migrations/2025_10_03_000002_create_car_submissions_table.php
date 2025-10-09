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
        Schema::create('car_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('asking_price', 10, 2)->nullable();
            $table->integer('year');
            $table->string('trim')->nullable();
            $table->integer('doors')->nullable();
            $table->integer('mileage');
            $table->string('origin_country');
            $table->string('condition_type')->default('used');
            $table->foreignId('car_brand_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->onDelete('set null');
            $table->foreignId('car_body_type_id')->constrained('body_types')->onDelete('cascade');
            $table->foreignId('car_condition_id')->nullable()->constrained('car_conditions')->nullOnDelete();
            $table->foreignId('mileage_type_id')->nullable()->constrained('mileage_types')->nullOnDelete();
            $table->foreignId('import_type_id')->nullable()->constrained('import_types')->nullOnDelete();
            $table->foreignId('fuel_type_id')->nullable()->constrained('fuel_types')->nullOnDelete();
            $table->foreignId('transmission_id')->nullable()->constrained('transmissions')->nullOnDelete();
            $table->foreignId('exterior_color_id')->nullable()->constrained('exterior_colors')->nullOnDelete();
            $table->foreignId('drive_type_id')->nullable()->constrained('drive_types')->nullOnDelete();
            $table->foreignId('warranty_status_id')->nullable()->constrained('warranty_statuses')->nullOnDelete();
            $table->foreignId('cylinder_id')->nullable()->constrained('cylinder_options')->nullOnDelete();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('car_listing_id')->nullable()->constrained()->onDelete('set null'); // If approved and published
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_submissions');
    }
};
