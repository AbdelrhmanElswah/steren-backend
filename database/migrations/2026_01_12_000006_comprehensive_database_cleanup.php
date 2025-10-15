<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes unused and redundant columns across multiple tables.
     * All removed columns are either:
     * 1. Redundant (data available elsewhere)
     * 2. Not being used in the application
     * 3. Replaced by better systems (e.g., Document system)
     */
    public function up(): void
    {
        // ========================================
        // 1. USERS TABLE CLEANUP
        // ========================================
        Schema::table('users', function (Blueprint $table) {
            $columnsToRemove = [];
            
            // device_permissions -> Now using UserDevicePermission table
            if (Schema::hasColumn('users', 'device_permissions')) {
                $columnsToRemove[] = 'device_permissions';
            }
            
            // Redundant calculated fields (can be computed from other tables)
            if (Schema::hasColumn('users', 'total_listings')) {
                $columnsToRemove[] = 'total_listings';
            }
            if (Schema::hasColumn('users', 'total_views')) {
                $columnsToRemove[] = 'total_views';
            }
            if (Schema::hasColumn('users', 'total_favorites')) {
                $columnsToRemove[] = 'total_favorites';
            }
            
            // Redundant with language_id FK
            if (Schema::hasColumn('users', 'language')) {
                $columnsToRemove[] = 'language';
            }
            
            // Not being used
            if (Schema::hasColumn('users', 'theme')) {
                $columnsToRemove[] = 'theme';
            }
            if (Schema::hasColumn('users', 'display_name')) {
                $columnsToRemove[] = 'display_name';
            }
            
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });

        // ========================================
        // 2. CAR_LISTINGS TABLE CLEANUP
        // ========================================
        Schema::table('car_listings', function (Blueprint $table) {
            $columnsToRemove = [];
            
            // Seller info redundant with user relationship
            if (Schema::hasColumn('car_listings', 'seller_name')) {
                $columnsToRemove[] = 'seller_name';
            }
            if (Schema::hasColumn('car_listings', 'seller_phone')) {
                $columnsToRemove[] = 'seller_phone';
            }
            if (Schema::hasColumn('car_listings', 'seller_email')) {
                $columnsToRemove[] = 'seller_email';
            }
            
            // Redundant with promotion system
            if (Schema::hasColumn('car_listings', 'is_sponsored')) {
                $columnsToRemove[] = 'is_sponsored';
            }
            
            // Redundant with CarListingAnalytics
            if (Schema::hasColumn('car_listings', 'views')) {
                $columnsToRemove[] = 'views';
            }
            
            // Redundant with user.partner_id
            if (Schema::hasColumn('car_listings', 'partner_id')) {
                // Drop index first
                $table->dropIndex(['partner_id']);
                $columnsToRemove[] = 'partner_id';
            }
            
            // Not implemented/used
            if (Schema::hasColumn('car_listings', 'studio_id')) {
                $table->dropForeign(['studio_id']);
                $columnsToRemove[] = 'studio_id';
            }
            
            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });

        // ========================================
        // 3. DEALER_APPLICATIONS TABLE CLEANUP
        // ========================================
        if (Schema::hasTable('dealer_applications')) {
            Schema::table('dealer_applications', function (Blueprint $table) {
                // Redundant with Document system
                if (Schema::hasColumn('dealer_applications', 'authorization_letter_path')) {
                    $table->dropColumn('authorization_letter_path');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Restores all removed columns with their original definitions.
     */
    public function down(): void
    {
        // ========================================
        // RESTORE USERS TABLE COLUMNS
        // ========================================
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'device_permissions')) {
                $table->json('device_permissions')->nullable();
            }
            if (!Schema::hasColumn('users', 'total_listings')) {
                $table->integer('total_listings')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_views')) {
                $table->integer('total_views')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_favorites')) {
                $table->integer('total_favorites')->default(0);
            }
            if (!Schema::hasColumn('users', 'language')) {
                $table->string('language')->nullable();
            }
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme')->nullable();
            }
            if (!Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name')->nullable();
            }
        });

        // ========================================
        // RESTORE CAR_LISTINGS TABLE COLUMNS
        // ========================================
        Schema::table('car_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('car_listings', 'seller_name')) {
                $table->string('seller_name')->nullable();
            }
            if (!Schema::hasColumn('car_listings', 'seller_phone')) {
                $table->string('seller_phone')->nullable();
            }
            if (!Schema::hasColumn('car_listings', 'seller_email')) {
                $table->string('seller_email')->nullable();
            }
            if (!Schema::hasColumn('car_listings', 'is_sponsored')) {
                $table->boolean('is_sponsored')->default(false);
            }
            if (!Schema::hasColumn('car_listings', 'views')) {
                $table->integer('views')->default(0);
            }
            if (!Schema::hasColumn('car_listings', 'partner_id')) {
                $table->integer('partner_id')->nullable();
                $table->index('partner_id');
            }
            if (!Schema::hasColumn('car_listings', 'studio_id')) {
                $table->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        // ========================================
        // RESTORE DEALER_APPLICATIONS TABLE COLUMNS
        // ========================================
        if (Schema::hasTable('dealer_applications')) {
            Schema::table('dealer_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('dealer_applications', 'authorization_letter_path')) {
                    $table->string('authorization_letter_path')->nullable();
                }
            });
        }
    }
};

