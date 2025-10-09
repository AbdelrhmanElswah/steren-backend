<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'active',
                'label' => 'Active',
                'color' => 'green',
                'description' => 'Car listing is active and available for sale'
            ],
            [
                'name' => 'inactive',
                'label' => 'Inactive',
                'color' => 'gray',
                'description' => 'Car listing is temporarily inactive'
            ],
            [
                'name' => 'sold',
                'label' => 'Sold',
                'color' => 'red',
                'description' => 'Car has been sold'
            ],
            [
                'name' => 'pending',
                'label' => 'Pending',
                'color' => 'yellow',
                'description' => 'Car listing is pending approval'
            ],
            [
                'name' => 'expired',
                'label' => 'Expired',
                'color' => 'orange',
                'description' => 'Car listing has expired'
            ],
            [
                'name' => 'boosted',
                'label' => 'Boosted',
                'color' => 'purple',
                'description' => 'Car listing is boosted for increased visibility'
            ],
            [
                'name' => 'archive',
                'label' => 'Archived',
                'color' => 'brown',
                'description' => 'Car listing has been archived'
            ],
            [
                'name' => 'suspend',
                'label' => 'Suspended',
                'color' => 'dark-red',
                'description' => 'Car listing has been suspended'
            ],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }

        $this->command->info('Statuses seeded successfully!');
    }
}

