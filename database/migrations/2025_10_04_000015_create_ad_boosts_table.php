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
        Schema::create('ad_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('boost_type'); // sponsored, featured, premium, etc.
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2);
            $table->string('currency')->default('IQD');
            $table->integer('duration_days'); // How long the boost lasts
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->json('boost_features')->nullable(); // Features included in this boost
            $table->json('payment_details')->nullable(); // Payment information
            $table->timestamps();
            
            $table->index(['car_listing_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_boosts');
    }
};
