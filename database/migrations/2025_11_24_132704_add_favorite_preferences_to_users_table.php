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
        Schema::table('users', function (Blueprint $table) {
            $table->json('fav_brand_ids')->nullable()->after('notification_preferences');
            $table->json('fav_models_ids')->nullable()->after('fav_brand_ids');
            $table->json('fav_citie_ids')->nullable()->after('fav_models_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fav_brand_ids', 'fav_models_ids', 'fav_citie_ids']);
        });
    }
};
