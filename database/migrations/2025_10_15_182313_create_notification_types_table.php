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
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'listing_approved', 'price_drop', 'new_match'
            $table->string('display_name'); // e.g., 'Listing Approved', 'Price Drop Alert', 'New Match Found'
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon name for UI
            $table->string('color')->default('#3B82F6'); // Color for UI
            $table->boolean('is_active')->default(true);
            $table->json('default_template')->nullable(); // Default message template
            $table->json('channels')->nullable(); // ['push', 'email', 'sms'] - which channels this type supports
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
