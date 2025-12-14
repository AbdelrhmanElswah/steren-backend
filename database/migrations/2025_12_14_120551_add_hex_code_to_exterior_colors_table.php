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
        Schema::table('exterior_colors', function (Blueprint $table) {
            $table->string('hex_code', 7)->nullable()->after('name_kr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exterior_colors', function (Blueprint $table) {
            $table->dropColumn('hex_code');
        });
    }
};
