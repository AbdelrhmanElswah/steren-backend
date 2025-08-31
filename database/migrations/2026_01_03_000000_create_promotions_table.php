<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('image_url');
            $table->string('call_to_action_text')->default('Sell Now');
            $table->string('call_to_action_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->string('type')->default('banner');
            $table->json('target_audience')->nullable(); // For targeting specific users
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
