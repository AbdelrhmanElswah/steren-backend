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
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('apple_id')->nullable()->unique()->after('firebase_uid');
            $table->string('google_id')->nullable()->unique()->after('apple_id');
            $table->string('provider')->nullable()->after('password'); // 'google', 'apple', 'email'
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'apple_id', 'google_id', 'provider']);
        });
    }
};
