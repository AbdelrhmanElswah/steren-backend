<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tracks slot purchases by users.
     * Purchased slots persist even when user has active subscription.
     */
    public function up(): void
    {
        Schema::create('slot_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Purchase details
            $table->integer('slots_purchased')->comment('Number of slots purchased');
            $table->decimal('amount_paid', 10, 2)->comment('Amount paid for the slots');
            $table->string('currency')->default('USD');
            $table->string('currency_symbol')->default('$');
            
            // Payment information
            $table->string('payment_method')->nullable()->comment('e.g., credit_card, paypal, wallet');
            $table->string('transaction_id')->nullable()->comment('External payment transaction ID');
            $table->string('payment_status')->default('completed')->comment('pending, completed, failed, refunded');
            $table->json('payment_details')->nullable()->comment('Additional payment metadata');
            
            // Purchase metadata
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_id');
            $table->index(['user_id', 'payment_status']);
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_purchases');
    }
};

