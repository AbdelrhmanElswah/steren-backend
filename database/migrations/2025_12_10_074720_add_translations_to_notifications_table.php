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
            // Add translation fields for title (title and message are used for English)
            $table->string('title_ar')->nullable()->after('title');
            $table->string('title_kr')->nullable()->after('title_ar');
            
            // Add translation fields for message (title and message are used for English)
            $table->text('message_ar')->nullable()->after('message');
            $table->text('message_kr')->nullable()->after('message_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'title_ar',
                'title_kr',
                'message_ar',
                'message_kr',
            ]);
        });
    }
};
