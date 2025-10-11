<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSubscription;
use App\Models\User;
use Carbon\Carbon;

class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired
                            {--suspend : Also suspend accounts whose grace period has ended}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired subscriptions and suspend accounts after grace period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');
        
        $expiredCount = 0;
        $gracePeriodCount = 0;
        $suspendedCount = 0;

        // Find subscriptions that have expired but grace period hasn't ended
        $expiredSubscriptions = UserSubscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            // Check if in grace period
            if ($subscription->isInGracePeriod()) {
                $gracePeriodCount++;
                $daysRemaining = $subscription->grace_period_days_remaining;
                
                $this->line("⚠ User #{$subscription->user_id}: In grace period ({$daysRemaining} day(s) remaining)");
                
                // Optionally send notification to user here
            } else {
                // Grace period has ended
                $expiredCount++;
                $subscription->endGracePeriod();
                
                $this->line("✗ User #{$subscription->user_id}: Grace period ended, subscription marked as expired");
                
                // Suspend account if --suspend option is provided
                if ($this->option('suspend')) {
                    $user = $subscription->user;
                    
                    if (!$user->isSuspended()) {
                        $user->suspend('Subscription expired without renewal after 15-day grace period');
                        $suspendedCount++;
                        
                        $this->error("⛔ User #{$user->id}: Account suspended");
                        
                        // Optionally send notification to user here
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Subscription check completed!');
        $this->info("✓ {$gracePeriodCount} subscription(s) in grace period");
        $this->info("✓ {$expiredCount} subscription(s) expired (grace period ended)");
        
        if ($this->option('suspend')) {
            $this->info("✓ {$suspendedCount} account(s) suspended");
        } else {
            $this->info("ℹ Use --suspend option to also suspend expired accounts");
        }

        return Command::SUCCESS;
    }
}
