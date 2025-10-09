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
        Schema::create('payment_processing', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique(); // TXN-20250318-0001 format
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('payment_method'); // FIB, Zain, Card, etc.
            $table->string('payment_gateway')->nullable(); // Mastercard, Visa, PayPal, etc.
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('IQD');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('payment_type'); // wallet_topup, subscription, ad_boost, etc.
            $table->json('payment_data')->nullable(); // Account number, full name, etc.
            $table->string('otp_code')->nullable(); // OTP for verification
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->string('reference_id')->nullable(); // External payment reference
            $table->text('failure_reason')->nullable(); // Reason for payment failure
            $table->json('gateway_response')->nullable(); // Response from payment gateway
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['wallet_id', 'status']);
            $table->index('transaction_id');
            $table->index('reference_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_processing');
    }
};
