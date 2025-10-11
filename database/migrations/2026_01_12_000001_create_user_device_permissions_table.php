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
        Schema::create('user_device_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Device permissions based on the screenshot
            $table->boolean('location')->default(false);
            $table->boolean('general_notifications')->default(false);
            $table->boolean('camera')->default(false);
            $table->boolean('photos')->default(false);
            
            // Additional metadata
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            // Ensure one record per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_device_permissions');
    }
};
