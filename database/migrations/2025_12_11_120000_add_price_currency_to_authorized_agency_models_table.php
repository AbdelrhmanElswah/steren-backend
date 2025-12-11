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
        Schema::table('authorized_agency_models', function (Blueprint $table) {
            $table->string('price_currency', 3)->default('USD')->after('price_start_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authorized_agency_models', function (Blueprint $table) {
            $table->dropColumn('price_currency');
        });
    }
};


