<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
use App\Models\ScheduledNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendScheduledNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $scheduledNotificationId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(int $scheduledNotificationId)
    {
        $this->scheduledNotificationId = $scheduledNotificationId;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseNotificationService $firebaseService): void
    {
        try {
            $scheduledNotification = ScheduledNotification::findOrFail($this->scheduledNotificationId);
            
            // Check if notification should still be sent
            if ($scheduledNotification->status !== 'pending') {
                Log::info('Scheduled notification already processed or cancelled', [
                    'scheduled_notification_id' => $this->scheduledNotificationId,
                    'status' => $scheduledNotification->status
                ]);
                return;
            }

            // Update status to processing
            $scheduledNotification->update(['status' => 'processing']);

            Log::info('Processing scheduled notification', [
                'scheduled_notification_id' => $this->scheduledNotificationId,
                'user_count' => count($scheduledNotification->user_ids),
                'title' => $scheduledNotification->title
            ]);

            $result = $firebaseService->sendToUsers(
                $scheduledNotification->user_ids,
                $scheduledNotification->title,
                $scheduledNotification->body,
                $scheduledNotification->data ?? [],
                $scheduledNotification->notification_type_id,
                $scheduledNotification->options ?? []
            );

            // Update status based on result
            $status = $result['success'] ? 'sent' : 'failed';
            $scheduledNotification->update([
                'status' => $status,
                'sent_at' => now(),
                'result' => $result
            ]);

            Log::info('Scheduled notification completed', [
                'scheduled_notification_id' => $this->scheduledNotificationId,
                'status' => $status,
                'success' => $result['success'],
                'message' => $result['message']
            ]);

        } catch (Exception $e) {
            Log::error('Scheduled notification job failed', [
                'scheduled_notification_id' => $this->scheduledNotificationId,
                'error' => $e->getMessage()
            ]);

            // Update status to failed
            try {
                $scheduledNotification = ScheduledNotification::find($this->scheduledNotificationId);
                if ($scheduledNotification) {
                    $scheduledNotification->update([
                        'status' => 'failed',
                        'result' => ['error' => $e->getMessage()]
                    ]);
                }
            } catch (Exception $updateException) {
                Log::error('Failed to update scheduled notification status', [
                    'scheduled_notification_id' => $this->scheduledNotificationId,
                    'error' => $updateException->getMessage()
                ]);
            }

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Scheduled notification job permanently failed', [
            'scheduled_notification_id' => $this->scheduledNotificationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Update status to failed
        try {
            $scheduledNotification = ScheduledNotification::find($this->scheduledNotificationId);
            if ($scheduledNotification) {
                $scheduledNotification->update([
                    'status' => 'failed',
                    'result' => ['error' => $exception->getMessage()]
                ]);
            }
        } catch (Exception $updateException) {
            Log::error('Failed to update scheduled notification status after failure', [
                'scheduled_notification_id' => $this->scheduledNotificationId,
                'error' => $updateException->getMessage()
            ]);
        }
    }
}