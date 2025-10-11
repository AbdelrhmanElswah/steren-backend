<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks slots for FREE USERS ONLY.
     * Subscribed users use the max_listings from their subscription plan.
     */
    public function up(): void
    {
        Schema::create('user_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Total slots = monthly_free_slot (0 or 1) + purchased_slots
            $table->integer('total_slots')->default(1)->comment('Total available slots (free + purchased)');
            $table->integer('consumed_slots')->default(0)->comment('Number of slots consumed (never decreases even if listing deleted)');
            $table->integer('available_slots')->default(1)->comment('Remaining slots (total - consumed)');
            
            // Purchased slots (persist even when user subscribes)
            $table->integer('purchased_slots')->default(0)->comment('Additional slots purchased by user');
            
            // Monthly free slot tracking (only for free users)
            $table->boolean('has_monthly_free_slot')->default(true)->comment('Whether user currently has their free monthly slot');
            $table->timestamp('last_free_slot_reset_at')->nullable()->comment('When the free slot was last given/reset');
            $table->timestamp('next_free_slot_reset_at')->nullable()->comment('When the next free slot will be available');
            
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index(['user_id', 'available_slots']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_slots');
    }
};

