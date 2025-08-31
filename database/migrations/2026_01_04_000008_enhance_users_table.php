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
        Schema::table('users', function (Blueprint $table) {
            // Profile fields
            $table->string('profile_picture')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('display_name')->nullable();
            $table->date('birthday')->nullable();
            
            // Settings
            $table->string('language')->default('en'); // en, ku, ar
            $table->string('theme')->default('system'); // light, dark, system
            
            // Account status
            $table->boolean('is_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            
            // Social authentication
            $table->string('social_provider')->nullable(); // apple, google, facebook
            $table->string('social_id')->nullable();
            
            // Account preferences
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('marketing_emails_enabled')->default(true);
            $table->json('notification_preferences')->nullable();
            
            // Device permissions
            $table->json('device_permissions')->nullable();
            
            // Account statistics
            $table->integer('total_listings')->default(0);
            $table->integer('total_views')->default(0);
            $table->integer('total_favorites')->default(0);
            
            // Timestamps
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_picture',
                'phone_number',
                'display_name',
                'birthday',
                'language',
                'theme',
                'is_verified',
                'email_verified_at',
                'phone_verified_at',
                'social_provider',
                'social_id',
                'notifications_enabled',
                'marketing_emails_enabled',
                'notification_preferences',
                'device_permissions',
                'total_listings',
                'total_views',
                'total_favorites',
                'last_login_at',
                'last_activity_at',
            ]);
        });
    }
};
