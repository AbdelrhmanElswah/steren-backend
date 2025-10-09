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
            // Add status_id column
            $table->foreignId('status_id')->nullable()->after('status')->constrained('statuses')->onDelete('set null');
            
            // Remove old status column (we'll migrate data first in seeder)
            // $table->dropColumn('status'); // We'll do this after data migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            
            // Restore old status column
            // $table->string('status')->default('active');
        });
    }
};
