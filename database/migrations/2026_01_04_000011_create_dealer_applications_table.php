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
        Schema::create('dealer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Account & Role Information
            $table->enum('seller_type', ['authorized', 'independent']);
            $table->string('owner_manager_name');
            $table->string('phone_number');
            $table->string('email');
            
            // Business Details (for both types)
            $table->string('dealership_company_name');
            $table->integer('vehicles_in_stock')->nullable();
            $table->integer('years_in_business')->nullable();
            $table->string('city_address');
            
            // Additional fields for Authorized Sellers only
            $table->string('automotive_brand_represented')->nullable();
            $table->string('authorization_letter_path')->nullable();
            $table->string('business_license_number')->nullable();
            
            // Application Status
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Subscription Plan Selection
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('payment_completed')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Terms and Conditions
            $table->boolean('terms_accepted')->default(false);
            $table->boolean('information_confirmed')->default(false);
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_applications');
    }
};
