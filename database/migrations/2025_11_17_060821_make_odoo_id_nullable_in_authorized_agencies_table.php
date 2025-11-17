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
        // Drop the unique constraint first
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->dropUnique(['odoo_id']);
        });
        
        // Make odoo_id nullable using raw SQL
        DB::statement('ALTER TABLE authorized_agencies MODIFY COLUMN odoo_id VARCHAR(255) NULL');
        
        // Re-add unique constraint (allows multiple NULLs in most databases)
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->unique('odoo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the unique constraint
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->dropUnique(['odoo_id']);
        });
        
        // Make odoo_id not nullable using raw SQL
        // First, set NULL values to empty string or a default value
        DB::statement("UPDATE authorized_agencies SET odoo_id = CONCAT('temp_', id) WHERE odoo_id IS NULL");
        
        // Then make it NOT NULL
        DB::statement('ALTER TABLE authorized_agencies MODIFY COLUMN odoo_id VARCHAR(255) NOT NULL');
        
        // Re-add unique constraint
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->unique('odoo_id');
        });
    }
};

