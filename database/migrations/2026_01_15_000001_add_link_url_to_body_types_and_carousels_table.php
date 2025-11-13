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
        Schema::table('carousels', function (Blueprint $table) {
            if (!Schema::hasColumn('carousels', 'link_url')) {
                $table->string('link_url')->nullable()->after('button_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carousels', function (Blueprint $table) {
            if (Schema::hasColumn('carousels', 'link_url')) {
                $table->dropColumn('link_url');
            }
        });
    }
};

