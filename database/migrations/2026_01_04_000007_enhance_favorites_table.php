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
        Schema::table('favorites', function (Blueprint $table) {
            // Add new fields for enhanced watch list functionality
            $table->text('notes')->nullable(); // User notes about the listing
            $table->boolean('notifications_enabled')->default(true); // Get notified about price changes
            $table->decimal('price_when_added', 12, 2)->nullable(); // Price when added to watch list
            $table->timestamp('viewed_at')->nullable(); // Last time user viewed this listing
            $table->integer('view_count')->default(0); // How many times user viewed this listing
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn([
                'notes',
                'notifications_enabled',
                'price_when_added',
                'viewed_at',
                'view_count',
            ]);
        });
    }
};
