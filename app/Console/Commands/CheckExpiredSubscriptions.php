<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update expired subscription status for cleanup and reporting (real-time validation uses expires_at column)';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $this->info('Checking for expired subscriptions...');

        $expiredCount = $subscriptionService->checkExpiredSubscriptions();

        $this->info("Updated {$expiredCount} expired subscription(s).");

        return Command::SUCCESS;
    }
}

