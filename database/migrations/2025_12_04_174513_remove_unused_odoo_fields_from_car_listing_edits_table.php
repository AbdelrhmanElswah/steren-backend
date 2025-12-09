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
        Schema::table('car_listing_edits', function (Blueprint $table) {
            $columnsToDrop = [];
            
            // Check which columns exist before trying to drop them
            if (Schema::hasColumn('car_listing_edits', 'odoo_listing_id')) {
                $columnsToDrop[] = 'odoo_listing_id';
            }
            if (Schema::hasColumn('car_listing_edits', 'odoo_vehicle_id')) {
                $columnsToDrop[] = 'odoo_vehicle_id';
            }
            if (Schema::hasColumn('car_listing_edits', 'odoo_seller_id')) {
                $columnsToDrop[] = 'odoo_seller_id';
            }
            if (Schema::hasColumn('car_listing_edits', 'odoo_city_id')) {
                $columnsToDrop[] = 'odoo_city_id';
            }
            if (Schema::hasColumn('car_listing_edits', 'odoo_listing_state')) {
                $columnsToDrop[] = 'odoo_listing_state';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listing_edits', function (Blueprint $table) {
            $table->string('odoo_listing_id')->nullable()->after('odoo_car_listing_id');
            $table->string('odoo_vehicle_id')->nullable()->after('odoo_listing_id');
            $table->string('odoo_seller_id')->nullable()->after('odoo_vehicle_id');
            $table->string('odoo_city_id')->nullable()->after('odoo_seller_id');
            $table->string('odoo_listing_state')->nullable()->after('odoo_city_id');
        });
    }
};
