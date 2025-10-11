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
        Schema::table('car_inspections', function (Blueprint $table) {
            // Add individual inspection fields
            $table->boolean('right_side')->default(false)->after('car_listing_id');
            $table->boolean('left_side')->default(false)->after('right_side');
            $table->boolean('roof')->default(false)->after('left_side');
            $table->boolean('front')->default(false)->after('roof');
            $table->boolean('back')->default(false)->after('front');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_inspections', function (Blueprint $table) {
            $table->dropColumn(['right_side', 'left_side', 'roof', 'front', 'back']);
        });
    }
};

