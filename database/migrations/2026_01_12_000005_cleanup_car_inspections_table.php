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
        Schema::table('car_inspections', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['inspector_id']);
            
            $table->dropColumn([
                'inspection_results', // Redundant - we have individual boolean fields now
                'report_url',         // Redundant - we use Document system for files
                'score',             // Not being used or calculated
                'inspector_id',      // Not being used in current flow
            ]);
            
            // Add soft deletes if not exists
            if (!Schema::hasColumn('car_inspections', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_inspections', function (Blueprint $table) {
            // Restore removed columns
            $table->json('inspection_results')->nullable();
            $table->string('report_url')->nullable();
            $table->decimal('score', 3, 1)->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Drop soft deletes
            $table->dropSoftDeletes();
        });
    }
};

