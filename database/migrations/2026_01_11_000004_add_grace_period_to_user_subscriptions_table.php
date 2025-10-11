<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds grace period tracking for subscriptions.
     * Grace period: 15 days after expiration before account suspension.
     */
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Grace period configuration (defaults to 15 days)
            $table->integer('grace_period_days')->default(15)->after('expires_at')
                  ->comment('Number of days after expiration before account suspension');
            
            $table->timestamp('grace_period_ends_at')->nullable()->after('grace_period_days')
                  ->comment('When the grace period ends (expires_at + grace_period_days)');
            
            $table->boolean('is_in_grace_period')->default(false)->after('grace_period_ends_at')
                  ->comment('Whether subscription is currently in grace period');
            
            $table->index(['expires_at', 'is_in_grace_period']);
            $table->index('grace_period_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['grace_period_days', 'grace_period_ends_at', 'is_in_grace_period']);
        });
    }
};

