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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('odoo_id')->unique()->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('currency_symbol')->nullable();
            $table->string('billing_cycle');
            $table->json('features')->nullable();
            $table->integer('max_listings')->nullable();
            $table->integer('max_photos')->nullable();
            $table->integer('subscriber_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_whatsapp_phone')->default(false);
            $table->boolean('is_badge')->default(false);
            $table->boolean('is_search')->default(false);
            $table->boolean('is_similar_cars')->default(false);
            $table->boolean('is_dedicated_account_rep')->default(false);
            $table->boolean('is_analytics')->default(false);
            $table->boolean('is_top_picks')->default(false);
            $table->boolean('is_priority_support')->default(false);
            $table->boolean('is_you_may_also_like')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index('odoo_id');
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active'); // active, cancelled, expired
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
