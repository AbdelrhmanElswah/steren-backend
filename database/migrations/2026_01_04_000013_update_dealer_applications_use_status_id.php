<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the new status_id column
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('status')->constrained('statuses')->onDelete('set null');
        });

        // Migrate existing status data
        $statusMapping = [
            'pending' => 'pending',
            'under_review' => 'under_review',
            'approved' => 'approved',
            'rejected' => 'rejected',
        ];

        foreach ($statusMapping as $statusName => $statusName) {
            $status = DB::table('statuses')
                ->where('name', $statusName)
                ->where('type', 'dealer_application')
                ->first();
            
            if ($status) {
                DB::table('dealer_applications')
                    ->where('status', $statusName)
                    ->update(['status_id' => $status->id]);
            }
        }

        // Remove the old status column
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the status column
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending')->after('status_id');
        });

        // Migrate data back
        $statusMapping = [
            'pending' => 'pending',
            'under_review' => 'under_review',
            'approved' => 'approved',
            'rejected' => 'rejected',
        ];

        foreach ($statusMapping as $statusName => $statusName) {
            $status = DB::table('statuses')
                ->where('name', $statusName)
                ->where('type', 'dealer_application')
                ->first();
            
            if ($status) {
                DB::table('dealer_applications')
                    ->where('status_id', $status->id)
                    ->update(['status' => $statusName]);
            }
        }

        // Remove the status_id column
        Schema::table('dealer_applications', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};