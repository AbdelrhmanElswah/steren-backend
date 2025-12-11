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
        // Drop location_id from car_submissions if present
        if (Schema::hasTable('car_submissions') && Schema::hasColumn('car_submissions', 'location_id')) {
            Schema::table('car_submissions', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        // Drop location_id from car_listings if present (legacy)
        if (Schema::hasTable('car_listings') && Schema::hasColumn('car_listings', 'location_id')) {
            Schema::table('car_listings', function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        // Clean up references to legacy studios table before dropping it
        if (Schema::hasTable('car_listing_edits') && Schema::hasColumn('car_listing_edits', 'studio_id')) {
            Schema::table('car_listing_edits', function (Blueprint $table) {
                $table->dropForeign(['studio_id']);
                $table->dropColumn('studio_id');
            });
        }

        // Drop legacy studios table
        if (Schema::hasTable('studios')) {
            Schema::dropIfExists('studios');
        }

        // Drop legacy locations table
        if (Schema::hasTable('locations')) {
            Schema::dropIfExists('locations');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate legacy locations table (structure only)
        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('odoo_id')->nullable()->unique();
                $table->timestamps();
            });
        }

        // Recreate legacy studios table (structure only)
        if (!Schema::hasTable('studios')) {
            Schema::create('studios', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('logo_url')->nullable();
                $table->text('description')->nullable();
                $table->json('contact_info')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->string('tagline')->nullable();
                $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('cascade');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // Re-add location_id to car_submissions if missing
        if (Schema::hasTable('car_submissions') && !Schema::hasColumn('car_submissions', 'location_id')) {
            Schema::table('car_submissions', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('cylinder_id')->constrained('locations')->onDelete('cascade');
            });
        }

        // Re-add location_id to car_listings if missing
        if (Schema::hasTable('car_listings') && !Schema::hasColumn('car_listings', 'location_id')) {
            Schema::table('car_listings', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('user_id')->constrained('locations')->onDelete('cascade');
            });
        }
    }
};

