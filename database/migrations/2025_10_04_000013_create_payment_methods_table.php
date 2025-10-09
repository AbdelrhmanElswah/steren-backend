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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // FIB, Zain, Card, etc.
            $table->string('display_name'); // Display name for UI
            $table->string('type'); // bank, mobile_money, card, digital_wallet
            $table->string('gateway')->nullable(); // Payment gateway name
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('supported_currencies')->nullable(); // Array of supported currencies
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('configuration')->nullable(); // Gateway-specific configuration
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
