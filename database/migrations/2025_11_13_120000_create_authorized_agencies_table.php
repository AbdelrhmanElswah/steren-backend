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
        Schema::create('authorized_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('odoo_id')->unique();
            $table->string('name');
            $table->string('partner_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('partner_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorized_agencies');
    }
};

