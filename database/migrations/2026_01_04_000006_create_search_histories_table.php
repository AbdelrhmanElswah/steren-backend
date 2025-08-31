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
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title'); // e.g., "Mazda rx7"
            $table->string('search_query')->nullable(); // The actual search term
            $table->json('filters')->nullable(); // Store all filter parameters
            $table->json('sort_options')->nullable(); // Store sorting preferences
            $table->boolean('is_saved')->default(false); // Whether this is a saved search
            $table->boolean('notifications_enabled')->default(false); // "Get notified about new offers"
            $table->integer('search_count')->default(1); // How many times this search was performed
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_saved']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
