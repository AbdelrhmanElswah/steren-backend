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
        // Add missing fields to car_brands table if needed
        if (!Schema::hasColumn('car_brands', 'logo_url')) {
            Schema::table('car_brands', function (Blueprint $table) {
                $table->string('logo_url')->nullable()->after('name');
            });
        }

        // Add missing fields to body_types table if needed
        if (!Schema::hasColumn('body_types', 'icon_url')) {
            Schema::table('body_types', function (Blueprint $table) {
                $table->string('icon_url')->nullable()->after('name');
            });
        }

        // Add missing fields to cities table if needed
        if (!Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('name');
            });
        }

        // Add missing fields to media table if needed
        if (!Schema::hasColumn('media', 'order')) {
            Schema::table('media', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('media');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropColumn('logo_url');
        });

        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn('icon_url');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
