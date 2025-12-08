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
        // Car Brands
        Schema::table('car_brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Car Models
        Schema::table('car_models', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Cities/States
        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Body Types
        Schema::table('body_types', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Subscription Plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
        });

        // Budgets
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });

        // Carousels
        Schema::table('carousels', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_kr')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('description_kr')->nullable()->after('description_ar');
            $table->string('button_text_ar')->nullable()->after('button_text');
            $table->string('button_text_kr')->nullable()->after('button_text_ar');
        });

        // Authorized Agencies
        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_kr')->nullable()->after('name_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_brands', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });

        Schema::table('body_types', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr', 'description_ar', 'description_kr']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });

        Schema::table('carousels', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'title_kr', 'description_ar', 'description_kr', 'button_text_ar', 'button_text_kr']);
        });

        Schema::table('authorized_agencies', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_kr']);
        });
    }
};
