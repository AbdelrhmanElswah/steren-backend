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
        // Add missing fields to media table if needed
        if (!Schema::hasColumn('media', 'order')) {
            Schema::table('media', function (Blueprint $table) {
                $table->integer('order')->default(0)->after('media');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
