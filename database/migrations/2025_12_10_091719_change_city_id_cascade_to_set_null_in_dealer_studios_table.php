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
        Schema::table('dealer_studios', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['city_id']);
            
            // Make city_id nullable (in case it's not already)
            $table->foreignId('city_id')->nullable()->change();
            
            // Re-add the foreign key with set null on delete
            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealer_studios', function (Blueprint $table) {
            // Drop the set null foreign key
            $table->dropForeign(['city_id']);
            
            // Re-add the cascade foreign key (original behavior)
            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->onDelete('cascade');
        });
    }
};
