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
            $table->string('odoo_id')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('odoo_id');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropColumn(['odoo_id', 'description', 'is_active']);
        });
    }
};
