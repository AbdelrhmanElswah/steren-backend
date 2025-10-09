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
            // API specific fields
            $table->integer('partner_id')->nullable()->after('odoo_id');
            $table->string('price_currency', 3)->default('USD')->after('price');
            $table->string('gcc_import')->nullable()->after('origin_country');
            $table->string('plate_status')->nullable()->after('gcc_import');
            
            // Add index for partner_id for better performance
            $table->index('partner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropIndex(['partner_id']);
            $table->dropColumn([
                'partner_id',
                'price_currency', 
                'gcc_import',
                'plate_status'
            ]);
        });
    }
};