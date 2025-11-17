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
            if (!Schema::hasColumn('car_listings', 'studio_id')) {
                $table->foreignId('studio_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('dealer_studios')
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
            if (Schema::hasColumn('car_listings', 'studio_id')) {
                $table->dropForeign(['studio_id']);
                $table->dropColumn('studio_id');
            }
        });
    }
};
