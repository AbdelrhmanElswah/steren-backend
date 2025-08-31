<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add VIN to car_listings table
        Schema::table('car_listings', function (Blueprint $table) {
            $table->string('vin', 17)->nullable()->after('title');
            $table->index('vin');
        });

        // Add VIN to car_submissions table
        Schema::table('car_submissions', function (Blueprint $table) {
            $table->string('vin', 17)->nullable()->after('title');
            $table->index('vin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropIndex(['vin']);
            $table->dropColumn('vin');
        });

        Schema::table('car_submissions', function (Blueprint $table) {
            $table->dropIndex(['vin']);
            $table->dropColumn('vin');
        });
    }
};
