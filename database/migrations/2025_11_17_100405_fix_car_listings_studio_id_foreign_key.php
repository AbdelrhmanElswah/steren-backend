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
        Schema::table('car_listings', function (Blueprint $table) {
            if (Schema::hasColumn('car_listings', 'studio_id')) {
                try {
                    $table->dropForeign(['studio_id']);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE car_listings DROP FOREIGN KEY IF EXISTS car_listings_studio_id_foreign');
                }
                
                $table->foreign('studio_id')
                    ->references('id')
                    ->on('dealer_studios')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            // Drop the dealer_studios foreign key
            $table->dropForeign(['studio_id']);
            
            // Restore the old studios foreign key (if needed)
            $table->foreign('studio_id')
                ->references('id')
                ->on('studios')
                ->onDelete('set null');
        });
    }
};
