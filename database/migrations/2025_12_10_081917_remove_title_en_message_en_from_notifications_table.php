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
        Schema::table('notifications', function (Blueprint $table) {
            // Drop title_en and message_en columns if they exist (from previous migration)
            // These were removed because we use title and message fields directly for English
            if (Schema::hasColumn('notifications', 'title_en')) {
                $table->dropColumn('title_en');
            }
            if (Schema::hasColumn('notifications', 'message_en')) {
                $table->dropColumn('message_en');
            }
            
            // Make title and message non-nullable since they're used for English
            if (Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable(false)->change();
            }
            if (Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable(false)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Re-add title_en and message_en if rolling back
            if (!Schema::hasColumn('notifications', 'title_en')) {
                $table->string('title_en')->nullable()->after('title');
            }
            if (!Schema::hasColumn('notifications', 'message_en')) {
                $table->text('message_en')->nullable()->after('message');
            }
            
            // Make title and message nullable again
            if (Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable()->change();
            }
            if (Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->change();
            }
        });
    }
};
