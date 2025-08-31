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
        Schema::create('car_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_part_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('clean');
            $table->text('description')->nullable();
            $table->decimal('repair_cost', 10, 2)->nullable();
            $table->date('repair_date')->nullable();
            $table->string('repair_shop')->nullable();
            $table->json('damage_details')->nullable(); // For detailed damage information
            $table->string('image_url')->nullable(); // For damage photos
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_damages');
    }
};
