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
        Schema::table('carfax_reports', function (Blueprint $table) {
            $table->string('odoo_id')->nullable()->unique()->after('id');
            $table->index('odoo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carfax_reports', function (Blueprint $table) {
            $table->dropIndex(['odoo_id']);
            $table->dropColumn('odoo_id');
        });
    }
};
