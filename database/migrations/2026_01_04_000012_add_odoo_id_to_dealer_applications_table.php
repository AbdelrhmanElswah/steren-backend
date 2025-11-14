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
        Schema::table('dealer_applications', function (Blueprint $table) {
            // Check if user_id exists (old) or partner_id exists (new) to determine where to place odoo_id
            $afterColumn = Schema::hasColumn('dealer_applications', 'partner_id') 
                ? 'partner_id' 
                : (Schema::hasColumn('dealer_applications', 'user_id') ? 'user_id' : 'id');
            $table->string('odoo_id')->nullable()->after($afterColumn);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->dropColumn('odoo_id');
        });
    }
};