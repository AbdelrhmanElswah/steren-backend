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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type'); // credit, debit, refund, transfer
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('currency')->default('IQD');
            $table->string('payment_method')->nullable(); // FIB, Zain, Card, etc.
            $table->string('payment_gateway')->nullable(); // Mastercard, Visa, PayPal, etc.
            $table->string('reference_id')->nullable(); // External payment reference
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->text('description')->nullable();
            $table->json('payment_details')->nullable(); // Additional payment information
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'transaction_type']);
            $table->index(['wallet_id', 'status']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
