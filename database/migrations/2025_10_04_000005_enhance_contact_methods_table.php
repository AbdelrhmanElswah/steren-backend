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
        Schema::table('contact_methods', function (Blueprint $table) {
            // Add new fields for enhanced contact methods
            $table->string('action_type')->nullable(); // e.g., 'sms', 'email', 'call', 'whatsapp'
            $table->string('action_url')->nullable(); // URL template for the action
            $table->boolean('requires_phone')->default(false); // Whether this method requires phone number
            $table->boolean('requires_email')->default(false); // Whether this method requires email
            $table->text('instructions')->nullable(); // Instructions for using this contact method
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_methods', function (Blueprint $table) {
            $table->dropColumn([
                'action_type',
                'action_url',
                'requires_phone',
                'requires_email',
                'instructions',
            ]);
        });
    }
};
