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
            // Store Odoo response data
            $table->json('odoo_response')->nullable()->after('odoo_id');
            
            // Store specific Odoo IDs from response
            $table->string('odoo_listing_id')->nullable()->after('odoo_response');
            $table->string('odoo_vehicle_id')->nullable()->after('odoo_listing_id');
            $table->string('odoo_seller_id')->nullable()->after('odoo_vehicle_id');
            $table->string('odoo_city_id')->nullable()->after('odoo_seller_id');
            
            // Store Odoo listing state
            $table->string('odoo_listing_state')->nullable()->after('odoo_city_id');
            
            // Add indexes for better performance
            $table->index('odoo_listing_id');
            $table->index('odoo_vehicle_id');
            $table->index('odoo_seller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropIndex(['odoo_listing_id']);
            $table->dropIndex(['odoo_vehicle_id']);
            $table->dropIndex(['odoo_seller_id']);
            
            $table->dropColumn([
                'odoo_response',
                'odoo_listing_id',
                'odoo_vehicle_id',
                'odoo_seller_id',
                'odoo_city_id',
                'odoo_listing_state'
            ]);
        });
    }
};
