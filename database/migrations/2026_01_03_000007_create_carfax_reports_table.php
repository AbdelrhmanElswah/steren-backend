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
        Schema::create('carfax_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_listing_id')->constrained()->onDelete('cascade');
            $table->string('vin', 17);
            $table->string('report_number')->unique();
            $table->date('report_date');
            $table->string('status')->default('pending');
            $table->json('report_data')->nullable(); // Carfax report data
            $table->string('report_url')->nullable(); // Link to Carfax report
            $table->integer('accident_count')->default(0);
            $table->integer('owner_count')->default(0);
            $table->boolean('has_clean_title')->default(true);
            $table->boolean('has_service_history')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carfax_reports');
    }
};
