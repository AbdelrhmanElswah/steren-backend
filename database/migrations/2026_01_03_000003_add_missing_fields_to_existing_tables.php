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
        // Add missing fields to body_types table if needed
        if (!Schema::hasColumn('body_types', 'icon_url')) {
            Schema::table('body_types', function (Blueprint $table) {
                $table->string('icon_url')->nullable()->after('name');
            });
        }

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
        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn('icon_url');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
