<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds account suspension tracking for users whose subscription has expired
     * and they haven't renewed within the 15-day grace period.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account status and suspension
            $table->boolean('is_suspended')->default(false)->after('is_dealer')
                  ->comment('Account suspended due to non-payment after grace period');
            
            $table->timestamp('suspended_at')->nullable()->after('is_suspended')
                  ->comment('When the account was suspended');
            
            $table->text('suspension_reason')->nullable()->after('suspended_at')
                  ->comment('Reason for suspension');
            
            $table->index('is_suspended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspended_at', 'suspension_reason']);
        });
    }
};

