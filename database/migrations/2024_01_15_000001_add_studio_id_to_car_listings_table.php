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
            $table->foreignId('studio_id')->nullable()->constrained('dealer_studios')->onDelete('set null');
            $table->index('studio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropForeign(['studio_id']);
            $table->dropIndex(['studio_id']);
            $table->dropColumn('studio_id');
        });
    }
};
