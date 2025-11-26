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
        Schema::create('car_listing_views_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained('car_listings')->onDelete('cascade');
            $table->string('ip_address');
            $table->date('view_date');
            $table->timestamps();
            
            // Index for efficient lookups (using shorter names to avoid MySQL 64-char limit)
            $table->index(['car_listing_id', 'ip_address', 'view_date'], 'clvt_listing_ip_date_idx');
            $table->index(['car_listing_id', 'ip_address', 'created_at'], 'clvt_listing_ip_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_listing_views_track');
    }
};
