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
        Schema::table('locations', function (Blueprint $table) {
            // Check if odoo_id exists to determine where to place city_id
            $afterColumn = Schema::hasColumn('locations', 'odoo_id') ? 'odoo_id' : 'name';
            $table->foreignId('city_id')->nullable()->after($afterColumn)->constrained('cities')->onDelete('set null');
            $table->index('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
