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
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->json('contact_info')->nullable(); // For phone, email, address
            $table->boolean('is_featured')->default(false);
            $table->string('tagline')->nullable(); // For "YOUR TRUSTED PARTNER"
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studios');
    }
};
