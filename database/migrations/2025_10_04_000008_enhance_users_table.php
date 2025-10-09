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
            // Social authentication
            if (!Schema::hasColumn('users', 'social_provider')) {
                $table->string('social_provider')->nullable(); // apple, google, facebook
            }
            if (!Schema::hasColumn('users', 'social_id')) {
                $table->string('social_id')->nullable();
            }
            
            // Account preferences
            if (!Schema::hasColumn('users', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true);
            }
            if (!Schema::hasColumn('users', 'marketing_emails_enabled')) {
                $table->boolean('marketing_emails_enabled')->default(true);
            }
            if (!Schema::hasColumn('users', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable();
            }
            
            // Device permissions
            if (!Schema::hasColumn('users', 'device_permissions')) {
                $table->json('device_permissions')->nullable();
            }
            
            // Account statistics
            if (!Schema::hasColumn('users', 'total_listings')) {
                $table->integer('total_listings')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_views')) {
                $table->integer('total_views')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_favorites')) {
                $table->integer('total_favorites')->default(0);
            }
            
            // Timestamps
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('users', 'social_provider')) {
                $columnsToDrop[] = 'social_provider';
            }
            if (Schema::hasColumn('users', 'social_id')) {
                $columnsToDrop[] = 'social_id';
            }
            if (Schema::hasColumn('users', 'notifications_enabled')) {
                $columnsToDrop[] = 'notifications_enabled';
            }
            if (Schema::hasColumn('users', 'marketing_emails_enabled')) {
                $columnsToDrop[] = 'marketing_emails_enabled';
            }
            if (Schema::hasColumn('users', 'notification_preferences')) {
                $columnsToDrop[] = 'notification_preferences';
            }
            if (Schema::hasColumn('users', 'device_permissions')) {
                $columnsToDrop[] = 'device_permissions';
            }
            if (Schema::hasColumn('users', 'total_listings')) {
                $columnsToDrop[] = 'total_listings';
            }
            if (Schema::hasColumn('users', 'total_views')) {
                $columnsToDrop[] = 'total_views';
            }
            if (Schema::hasColumn('users', 'total_favorites')) {
                $columnsToDrop[] = 'total_favorites';
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $columnsToDrop[] = 'last_login_at';
            }
            if (Schema::hasColumn('users', 'last_activity_at')) {
                $columnsToDrop[] = 'last_activity_at';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
