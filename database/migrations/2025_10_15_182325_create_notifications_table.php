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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('type_id')->constrained('notification_types')->onDelete('cascade');
            $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data for the notification
            $table->string('channel')->default('push'); // push, email, sms
            $table->timestamp('sent_at')->nullable(); // When notification was sent
            $table->timestamp('read_at')->nullable(); // When user read the notification
            $table->timestamp('clicked_at')->nullable(); // When user clicked the notification
            $table->json('delivery_status')->nullable(); // Delivery status for different channels
            $table->string('external_id')->nullable(); // External service ID (FCM, email service, etc.)
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['type_id', 'created_at']);
            $table->index(['status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
