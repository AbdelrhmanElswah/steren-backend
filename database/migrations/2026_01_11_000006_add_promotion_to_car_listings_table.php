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
        Schema::table('car_listings', function (Blueprint $table) {
            $table->foreignId('listing_promotion_id')->nullable()->after('user_id')->constrained('listing_promotions')->onDelete('set null');
            $table->decimal('promotion_price_paid', 10, 2)->nullable()->after('listing_promotion_id');
            $table->string('promotion_currency', 10)->default('IQD')->after('promotion_price_paid');
            $table->timestamp('promotion_expires_at')->nullable()->after('promotion_currency');
            $table->boolean('is_promoted')->default(false)->after('promotion_expires_at');
            $table->timestamp('promotion_started_at')->nullable()->after('is_promoted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_listings', function (Blueprint $table) {
            $table->dropForeign(['listing_promotion_id']);
            $table->dropColumn([
                'listing_promotion_id',
                'promotion_price_paid',
                'promotion_currency',
                'promotion_expires_at',
                'is_promoted',
                'promotion_started_at'
            ]);
        });
    }
};
