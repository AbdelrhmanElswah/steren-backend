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
        Schema::create('contact_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // SMS, Email, Call, WhatsApp
            $table->string('icon')->nullable(); // Icon for the contact method
            $table->string('color')->nullable(); // Color for the button (e.g., green for WhatsApp)
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->json('settings')->nullable(); // Additional settings for each method
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_methods');
    }
};
