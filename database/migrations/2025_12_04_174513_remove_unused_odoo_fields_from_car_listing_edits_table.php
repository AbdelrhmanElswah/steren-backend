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
            $table->dropColumn([
                'odoo_listing_id',
                'odoo_vehicle_id',
                'odoo_seller_id',
                'odoo_city_id',
                'odoo_listing_state',
            ]);
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
