<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarListingContactMethodTable extends Migration
{
    public function up(): void
    {
        Schema::create('car_listing_contact_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_method_id')->constrained()->onDelete('cascade');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('car_listing_contact_method');
    }
}
