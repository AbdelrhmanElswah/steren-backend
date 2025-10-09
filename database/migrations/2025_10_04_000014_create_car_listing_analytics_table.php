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
        Schema::create('car_listing_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->string('metric_type'); // views, shares, call_taps, whatsapp_clicks, sms, email, watchlist_saves
            $table->integer('metric_value')->default(1);
            $table->date('date');
            $table->json('additional_data')->nullable(); // Additional context like user agent, location, etc.
            $table->timestamps();
            
            $table->index(['car_listing_id', 'metric_type', 'date']);
            $table->index(['car_listing_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listing_analytics');
    }
};
