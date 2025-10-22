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
            // Add missing Odoo columns only if they don't exist
            if (!Schema::hasColumn('car_listings', 'odoo_listing_id')) {
                $table->string('odoo_listing_id')->nullable()->after('odoo_id');
            }
            if (!Schema::hasColumn('car_listings', 'odoo_vehicle_id')) {
                $table->string('odoo_vehicle_id')->nullable()->after('odoo_listing_id');
            }
            if (!Schema::hasColumn('car_listings', 'odoo_seller_id')) {
                $table->string('odoo_seller_id')->nullable()->after('odoo_vehicle_id');
            }
            if (!Schema::hasColumn('car_listings', 'odoo_city_id')) {
                $table->string('odoo_city_id')->nullable()->after('odoo_seller_id');
            }
            if (!Schema::hasColumn('car_listings', 'odoo_listing_state')) {
                $table->string('odoo_listing_state')->nullable()->after('odoo_city_id');
            }
        });
        
        // Add indexes for better performance (only if columns exist)
        Schema::table('car_listings', function (Blueprint $table) {
            if (Schema::hasColumn('car_listings', 'odoo_listing_id')) {
                $table->index('odoo_listing_id');
            }
            if (Schema::hasColumn('car_listings', 'odoo_vehicle_id')) {
                $table->index('odoo_vehicle_id');
            }
            if (Schema::hasColumn('car_listings', 'odoo_seller_id')) {
                $table->index('odoo_seller_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            // Drop indexes first
            if (Schema::hasColumn('car_listings', 'odoo_listing_id')) {
                $table->dropIndex(['odoo_listing_id']);
            }
            if (Schema::hasColumn('car_listings', 'odoo_vehicle_id')) {
                $table->dropIndex(['odoo_vehicle_id']);
            }
            if (Schema::hasColumn('car_listings', 'odoo_seller_id')) {
                $table->dropIndex(['odoo_seller_id']);
            }
            
            // Drop columns
            $columnsToDrop = [];
            if (Schema::hasColumn('car_listings', 'odoo_listing_id')) {
                $columnsToDrop[] = 'odoo_listing_id';
            }
            if (Schema::hasColumn('car_listings', 'odoo_vehicle_id')) {
                $columnsToDrop[] = 'odoo_vehicle_id';
            }
            if (Schema::hasColumn('car_listings', 'odoo_seller_id')) {
                $columnsToDrop[] = 'odoo_seller_id';
            }
            if (Schema::hasColumn('car_listings', 'odoo_city_id')) {
                $columnsToDrop[] = 'odoo_city_id';
            }
            if (Schema::hasColumn('car_listings', 'odoo_listing_state')) {
                $columnsToDrop[] = 'odoo_listing_state';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};