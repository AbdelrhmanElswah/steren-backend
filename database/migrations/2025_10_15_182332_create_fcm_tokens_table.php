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
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token')->unique(); // FCM registration token
            $table->string('device_id')->nullable(); // Device identifier
            $table->string('device_type')->default('mobile'); // mobile, tablet, web
            $table->string('platform')->nullable(); // ios, android, web
            $table->string('app_version')->nullable(); // App version
            $table->string('os_version')->nullable(); // Operating system version
            $table->json('device_info')->nullable(); // Additional device information
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable(); // Last time token was used
            $table->timestamp('expires_at')->nullable(); // Token expiration
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'is_active']);
            $table->index(['device_id']);
            $table->index(['platform']);
            $table->index(['last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
