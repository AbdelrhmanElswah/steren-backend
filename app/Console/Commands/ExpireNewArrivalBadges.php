<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CarListing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExpireNewArrivalBadges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listings:expire-new-arrivals
                            {--days=14 : Number of days after approval to expire the badge}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire new arrival badges for listings that were approved more than 2 weeks ago';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $expirationDate = Carbon::now()->subDays($days);
        
        $this->info("Checking for new arrival badges to expire (approved before {$expirationDate->format('Y-m-d H:i:s')})...");

        // Get all listings with is_new_arrival = true that were approved more than $days ago
        $listings = CarListing::where('is_new_arrival', true)
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $expirationDate)
            ->get();

        if ($listings->isEmpty()) {
            $this->info('No new arrival badges to expire.');
            return Command::SUCCESS;
        }

        $expiredCount = 0;

        foreach ($listings as $listing) {
            $listing->update(['is_new_arrival' => false]);
            $expiredCount++;
            
            // Handle approved_at as string or Carbon instance
            $approvedAtFormatted = $listing->approved_at instanceof Carbon 
                ? $listing->approved_at->format('Y-m-d H:i:s')
                : Carbon::parse($listing->approved_at)->format('Y-m-d H:i:s');
            
            $this->line("✓ Expired new arrival badge for listing #{$listing->id} (approved: {$approvedAtFormatted})");
            
            Log::info('New arrival badge expired', [
                'listing_id' => $listing->id,
                'approved_at' => $listing->approved_at,
                'expired_at' => now()
            ]);
        }

        $this->newLine();
        $this->info("Successfully expired {$expiredCount} new arrival badge(s)!");

        return Command::SUCCESS;
    }
}
