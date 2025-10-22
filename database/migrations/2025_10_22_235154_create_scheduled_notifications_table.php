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
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->json('user_ids'); // Array of user IDs to send to
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // Additional data payload
            $table->json('options')->nullable(); // Notification options
            $table->unsignedBigInteger('notification_type_id')->nullable();
            // Foreign key constraint will be added later if needed
            $table->datetime('scheduled_at');
            $table->datetime('sent_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->json('result')->nullable(); // Store the result of the notification
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
            $table->index('notification_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
    }
};