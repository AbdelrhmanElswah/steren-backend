<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('safety_tips', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('icon')->nullable(); // For lightbulb icon or other icons
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->string('category')->default('general');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_tips');
    }
};
