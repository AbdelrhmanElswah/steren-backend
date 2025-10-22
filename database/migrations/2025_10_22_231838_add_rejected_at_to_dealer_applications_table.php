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
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->ttamp('rejected_at')->nullable()->after('approved_at');imes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
