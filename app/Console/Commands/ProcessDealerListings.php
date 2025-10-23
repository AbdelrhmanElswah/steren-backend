<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\AutoStudioService;
use App\Services\SubscriptionService;

class ProcessDealerListings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dealers:process-listings {--user-id= : Process specific user ID} {--all : Process all dealers}';

    /**
     * The console command description.
     */
    protected $description = 'Process existing dealer listings to auto-associate with studios and promote to top picks';

    protected AutoStudioService $autoStudioService;
    protected SubscriptionService $subscriptionService;

    public function __construct(AutoStudioService $autoStudioService, SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->autoStudioService = $autoStudioService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('user-id')) {
            $this->processSpecificUser($this->option('user-id'));
        } elseif ($this->option('all')) {
            $this->processAllDealers();
        } else {
            $this->error('Please specify --user-id=ID or --all');
            return 1;
        }

        return 0;
    }

    protected function processSpecificUser(int $userId): void
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return;
        }

        // Check if user is a verified dealer (strict validation for processing)
        if (!$user->isVerifiedDealer()) {
            $this->error("User {$user->full_name} is not a verified dealer. Verification required: approved application, documents verified, and valid business info.");
            return;
        }

        $this->info("Processing listings for dealer: {$user->full_name}");
        
        $this->autoStudioService->processExistingDealerListings($user);
        
        $this->info("✅ Processed listings for dealer: {$user->full_name}");
    }

    protected function processAllDealers(): void
    {
        $this->info('Processing all dealers...');

        // Get all verified dealers (users with approved, active, verified dealer applications)
        $dealers = User::whereHas('dealerApplications', function ($query) {
            $query->whereHas('status', function ($q) {
                $q->where('name', 'approved')
                  ->where('type', 'dealer_application');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
        })->get();

        $this->info("Found {$dealers->count()} dealers");

        $progressBar = $this->output->createProgressBar($dealers->count());
        $progressBar->start();

        foreach ($dealers as $dealer) {
            try {
                $this->autoStudioService->processExistingDealerListings($dealer);
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("Error processing dealer {$dealer->id}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Processed all dealers");
    }
}
