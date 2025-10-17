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
        Schema::table('car_brands', function (Blueprint $table) {
            if (!Schema::hasColumn('car_brands', 'odoo_id')) {
                $table->string('odoo_id')->nullable()->unique()->after('id');
                $table->index('odoo_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropIndex(['odoo_id']);
            $table->dropColumn('odoo_id');
        });
    }
};
