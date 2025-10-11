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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type'); // Polymorphic type (e.g., App\Models\DealerApplication, App\Models\CarListing)
            $table->unsignedBigInteger('documentable_id'); // Polymorphic ID
            $table->string('name'); // Original filename
            $table->string('path_file_name'); // S3 file path
            $table->string('file_type'); // pdf, doc, docx, xls, xlsx, etc.
            $table->string('mime_type'); // application/pdf, application/msword, etc.
            $table->integer('file_size'); // File size in bytes
            $table->string('category')->nullable(); // business_license, authorization_letter, inspection_report, etc.
            $table->string('description')->nullable(); // Optional description
            $table->boolean('is_verified')->default(false); // Whether document has been verified
            $table->unsignedBigInteger('verified_by')->nullable(); // User who verified the document
            $table->timestamp('verified_at')->nullable(); // When document was verified
            $table->text('verification_notes')->nullable(); // Notes from verification process
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['category']);
            $table->index(['is_verified']);
            $table->index(['file_type']);
            
            // Foreign key
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
