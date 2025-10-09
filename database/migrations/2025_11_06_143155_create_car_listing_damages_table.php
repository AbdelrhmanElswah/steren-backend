<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarListingDamagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('car_listing_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_part_id')->constrained()->onDelete('cascade');
            $table->string('condition');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_listing_damages');
    }
}
