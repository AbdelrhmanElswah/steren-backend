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
            // Add translation fields for title
            $table->string('title_en')->nullable()->after('title');
            $table->string('title_ar')->nullable()->after('title_en');
            $table->string('title_kr')->nullable()->after('title_ar');
            
            // Add translation fields for message
            $table->text('message_en')->nullable()->after('message');
            $table->text('message_ar')->nullable()->after('message_en');
            $table->text('message_kr')->nullable()->after('message_ar');
            
            // Make title and message nullable since we'll use translations
            $table->string('title')->nullable()->change();
            $table->text('message')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'title_en',
                'title_ar',
                'title_kr',
                'message_en',
                'message_ar',
                'message_kr',
            ]);
            
            // Restore title and message to not nullable
            $table->string('title')->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
