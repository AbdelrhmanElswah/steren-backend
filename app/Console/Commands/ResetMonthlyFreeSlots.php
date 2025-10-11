<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSlot;
use Carbon\Carbon;

class ResetMonthlyFreeSlots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:reset-monthly
                            {--force : Force reset for all users regardless of reset date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly free slot for all free users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly free slot reset...');
        $force = $this->option('force');

        // Get all user slots that need reset
        $query = UserSlot::query();
        
        if (!$force) {
            // Only reset slots where next_free_slot_reset_at has passed or is null
            $query->where(function($q) {
                $q->whereNull('next_free_slot_reset_at')
                  ->orWhere('next_free_slot_reset_at', '<=', Carbon::now());
            });
        }

        $userSlots = $query->get();
        $resetCount = 0;
        $skippedCount = 0;

        foreach ($userSlots as $userSlot) {
            // Check if user still needs reset
            if ($force || $userSlot->shouldResetMonthlyFreeSlot()) {
                $userSlot->resetMonthlyFreeSlot();
                $resetCount++;
                
                $this->line("✓ Reset slot for user #{$userSlot->user_id}");
            } else {
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("Monthly free slot reset completed!");
        $this->info("✓ Reset {$resetCount} user slots");
        
        if ($skippedCount > 0) {
            $this->info("✓ Skipped {$skippedCount} users (not yet due for reset)");
        }

        return Command::SUCCESS;
    }
}

