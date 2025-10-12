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
            $table->string('search_term');
            $table->json('filters')->nullable(); // Store search filters for future use
            $table->integer('result_count')->default(0); // Number of results found
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['user_id', 'created_at']);
            $table->index('search_term');
            
            // Prevent duplicate search terms for same user
            $table->unique(['user_id', 'search_term']);
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
