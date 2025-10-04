<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DealerNotificationService;

class CheckDealerSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dealer:check-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring and expired dealer subscriptions and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(DealerNotificationService $notificationService)
    {
        $this->info('Checking dealer subscriptions...');

        try {
            // Check for expiring subscriptions (within 3 days)
            $this->info('Checking for expiring subscriptions...');
            $notificationService->checkExpiringSubscriptions();
            
            // Check for expired subscriptions
            $this->info('Checking for expired subscriptions...');
            $notificationService->checkExpiredSubscriptions();
            
            $this->info('Dealer subscription check completed successfully.');
            
        } catch (\Exception $e) {
            $this->error('Error checking dealer subscriptions: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
