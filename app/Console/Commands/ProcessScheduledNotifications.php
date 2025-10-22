<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledNotificationJob;
use App\Models\ScheduledNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:process-scheduled {--limit=10 : Maximum number of notifications to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled notifications that are ready to be sent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Processing scheduled notifications (limit: {$limit})...");

        try {
            // Get pending notifications that are ready to be sent
            $scheduledNotifications = ScheduledNotification::pending()
                ->orderBy('scheduled_at', 'asc')
                ->limit($limit)
                ->get();

            if ($scheduledNotifications->isEmpty()) {
                $this->info('No scheduled notifications ready to be processed.');
                return 0;
            }

            $this->info("Found {$scheduledNotifications->count()} scheduled notifications ready to be processed.");

            $processed = 0;
            $failed = 0;

            foreach ($scheduledNotifications as $scheduledNotification) {
                try {
                    // Dispatch the job to process the notification
                    SendScheduledNotificationJob::dispatch($scheduledNotification->id);
                    
                    $this->line("Dispatched notification ID: {$scheduledNotification->id}");
                    $processed++;

                } catch (\Exception $e) {
                    $this->error("Failed to dispatch notification ID {$scheduledNotification->id}: {$e->getMessage()}");
                    $failed++;
                    
                    // Mark as failed
                    $scheduledNotification->markAsFailed($e->getMessage());
                }
            }

            $this->info("Processed: {$processed}, Failed: {$failed}");

            Log::info('Scheduled notifications processed', [
                'processed' => $processed,
                'failed' => $failed,
                'total' => $scheduledNotifications->count()
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to process scheduled notifications: {$e->getMessage()}");
            
            Log::error('Failed to process scheduled notifications', [
                'error' => $e->getMessage()
            ]);

            return 1;
        }
    }
}