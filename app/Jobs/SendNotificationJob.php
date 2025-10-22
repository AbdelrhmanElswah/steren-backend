<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $userIds;
    protected string $title;
    protected string $body;
    protected array $data;
    protected ?int $notificationTypeId;
    protected array $options;

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
    public function __construct(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        ?int $notificationTypeId = null,
        array $options = []
    ) {
        $this->userIds = $userIds;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->notificationTypeId = $notificationTypeId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseNotificationService $firebaseService): void
    {
        try {
            Log::info('Processing notification job', [
                'user_count' => count($this->userIds),
                'title' => $this->title,
                'notification_type_id' => $this->notificationTypeId
            ]);

            $result = $firebaseService->sendToUsers(
                $this->userIds,
                $this->title,
                $this->body,
                $this->data,
                $this->notificationTypeId,
                $this->options
            );

            Log::info('Notification job completed', [
                'success' => $result['success'],
                'message' => $result['message'],
                'success_count' => $result['success_count'] ?? 0,
                'failed_count' => $result['failed_count'] ?? 0
            ]);

        } catch (Exception $e) {
            Log::error('Notification job failed', [
                'error' => $e->getMessage(),
                'user_ids' => $this->userIds,
                'title' => $this->title
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Notification job permanently failed', [
            'error' => $exception->getMessage(),
            'user_ids' => $this->userIds,
            'title' => $this->title,
            'attempts' => $this->attempts()
        ]);
    }
}