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
        // Check if odoo_id column exists
        $columns = DB::select("SHOW COLUMNS FROM authorized_agencies LIKE 'odoo_id'");
        
        if (!empty($columns)) {
            // Column exists, drop unique index first if it exists
            $indexes = DB::select("SHOW INDEX FROM authorized_agencies WHERE Column_name = 'odoo_id'");
            
            if (!empty($indexes)) {
                // Get the index name (could be different names)
                $indexName = $indexes[0]->Key_name;
                DB::statement("ALTER TABLE authorized_agencies DROP INDEX {$indexName}");
            }
            
            // Drop the odoo_id column
            Schema::table('authorized_agencies', function (Blueprint $table) {
                $table->dropColumn('odoo_id');
            });
        }
        // If column doesn't exist, do nothing (already removed)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add odoo_id column if it doesn't exist (for rollback)
        $columns = DB::select("SHOW COLUMNS FROM authorized_agencies LIKE 'odoo_id'");
        
        if (empty($columns)) {
            Schema::table('authorized_agencies', function (Blueprint $table) {
                $table->string('odoo_id')->nullable()->unique()->after('id');
            });
        }
    }
};

