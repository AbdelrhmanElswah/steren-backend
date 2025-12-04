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
        Schema::create('car_listing_edits', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to original car listing
            $table->foreignId('car_listing_id')->constrained('car_listings')->onDelete('cascade');
            
            // Odoo-related fields
            $table->string('odoo_id')->nullable();
            $table->string('odoo_car_listing_id')->nullable(); // Computed from car_listing_id
            $table->string('odoo_listing_id')->nullable();
            $table->string('odoo_vehicle_id')->nullable();
            $table->string('odoo_seller_id')->nullable();
            $table->string('odoo_city_id')->nullable();
            $table->string('odoo_listing_state')->nullable();
            
            // Edit-specific fields
            $table->string('previous_state')->nullable(); // rejected, active, paused
            $table->string('edit_type')->nullable(); // 'edit' or 'resubmission' (computed from previous_state)
            $table->string('status')->default('pending'); // pending, approved, rejected
            
            // All CarListing fields (same structure)
            $table->string('title')->nullable();
            $table->string('vin', 17)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->integer('year')->nullable();
            $table->string('trim')->nullable();
            $table->integer('doors')->nullable();
            $table->integer('mileage')->nullable();
            $table->string('origin_country')->nullable();
            $table->string('gcc_import')->nullable();
            $table->string('plate_status')->nullable();
            $table->string('condition_type')->nullable();
            $table->boolean('has_warranty')->default(false);
            $table->boolean('is_inspected')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_insurance')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('studio_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('car_brand_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('car_body_type_id')->nullable()->constrained('body_types')->onDelete('set null');
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
            
            // Car specifications
            $table->string('engine_size')->nullable();
            $table->string('engine_unit')->nullable();
            $table->string('engine_type')->nullable();
            $table->integer('horsepower')->nullable();
            $table->string('torque')->nullable();
            $table->decimal('acceleration_0_100', 4, 1)->nullable();
            $table->integer('top_speed')->nullable();
            $table->integer('seating_capacity')->nullable();
            $table->decimal('mileage_efficiency', 5, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('forced_induction')->nullable();
            
            // Status and approval fields
            $table->string('operator_decision')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            
            // Feature flags
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            
            // Content fields
            $table->text('description')->nullable();
            $table->json('damaged_areas')->nullable();
            $table->json('features')->nullable();
            
            // Timestamps
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('featured_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            
            // Promotion fields
            $table->foreignId('listing_promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('promotion_price_paid', 10, 2)->nullable();
            $table->string('promotion_currency', 3)->nullable();
            $table->timestamp('promotion_expires_at')->nullable();
            $table->boolean('is_promoted')->default(false);
            $table->timestamp('promotion_started_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('car_listing_id');
            $table->index('odoo_id');
            $table->index('status');
            $table->index('edit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listing_edits');
    }
};
