<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WarrantyStatus;

class WarrantyStatusSeeder extends Seeder
{
    public function run(): void
    {
        $warrantyStatuses = [
            ['name' => 'Yes'],
            ['name' => 'No'],
        ];

        foreach ($warrantyStatuses as $status) {
            WarrantyStatus::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
