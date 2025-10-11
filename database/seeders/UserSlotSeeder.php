<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserSlot;
use App\Models\UserSubscription;
use Carbon\Carbon;

class UserSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates user_slots records for all existing free users (users without active subscription)
     */
    public function run(): void
    {
        $this->command->info('Initializing user slots for existing free users...');

        // Get all users
        $users = User::all();
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            // Skip if user already has a slot record
            if ($user->userSlot) {
                $skippedCount++;
                continue;
            }

            // Check if user has active subscription
            $hasActiveSubscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', Carbon::now());
                })
                ->exists();

            // Only create slot records for free users (no active subscription)
            if (!$hasActiveSubscription) {
                UserSlot::create([
                    'user_id' => $user->id,
                    'total_slots' => 1,
                    'consumed_slots' => 0,
                    'available_slots' => 1,
                    'purchased_slots' => 0,
                    'has_monthly_free_slot' => true,
                    'last_free_slot_reset_at' => Carbon::now(),
                    'next_free_slot_reset_at' => Carbon::now()->addMonth()->startOfMonth(),
                ]);

                $createdCount++;
            } else {
                $skippedCount++;
            }
        }

        $this->command->info("✓ Created {$createdCount} user slot records");
        $this->command->info("✓ Skipped {$skippedCount} users (already have slots or active subscription)");
        $this->command->info('User slot initialization completed!');
    }
}

