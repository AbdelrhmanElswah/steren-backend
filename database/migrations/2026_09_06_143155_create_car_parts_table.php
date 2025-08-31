<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarPartsTable extends Migration
{
    public function up(): void
    {
        Schema::create('car_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_parts');
    }
}
