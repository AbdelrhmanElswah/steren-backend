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
        Schema::table('body_types', function (Blueprint $table) {
            if (!Schema::hasColumn('body_types', 'odoo_id')) {
                $table->string('odoo_id')->nullable()->unique()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('body_types', function (Blueprint $table) {
            if (Schema::hasColumn('body_types', 'odoo_id')) {
                $table->dropColumn('odoo_id');
            }
        });
    }
};
