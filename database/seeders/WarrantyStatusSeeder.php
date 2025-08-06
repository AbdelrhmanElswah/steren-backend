<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WarrantyStatus;

class WarrantyStatusSeeder extends Seeder
{
    public function run(): void
    {
        WarrantyStatus::insert([
            ['name' => 'Yes'],
            ['name' => 'No'],
        ]);
    }
}
