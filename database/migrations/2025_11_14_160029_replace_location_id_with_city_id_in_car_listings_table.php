<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            // Add city_id column
            $table->foreignId('city_id')->nullable()->after('user_id')->constrained('cities')->onDelete('set null');
            $table->index('city_id');
        });

        // Migrate existing data: get city_id from location.city_id
        DB::statement('
            UPDATE car_listings
            INNER JOIN locations ON car_listings.location_id = locations.id
            SET car_listings.city_id = locations.city_id
            WHERE locations.city_id IS NOT NULL
        ');

        // Migrate by name matching if city_id is null
        DB::statement('
            UPDATE car_listings
            INNER JOIN locations ON car_listings.location_id = locations.id
            INNER JOIN cities ON locations.name = cities.name
            SET car_listings.city_id = cities.id
            WHERE car_listings.city_id IS NULL
        ');

        Schema::table('car_listings', function (Blueprint $table) {
            // Drop the foreign key constraint for location_id
            $table->dropForeign(['location_id']);
            // Drop the location_id column
            $table->dropColumn('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            // Re-add location_id column
            $table->foreignId('location_id')->nullable()->after('user_id')->constrained('locations')->onDelete('cascade');
        });

        // Migrate data back (find location by matching city name)
        DB::statement('
            UPDATE car_listings
            INNER JOIN cities ON car_listings.city_id = cities.id
            INNER JOIN locations ON cities.name = locations.name
            SET car_listings.location_id = locations.id
            WHERE car_listings.city_id IS NOT NULL
        ');

        Schema::table('car_listings', function (Blueprint $table) {
            // Drop city_id
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
