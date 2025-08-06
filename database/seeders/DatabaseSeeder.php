<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CarConditionSeeder::class,
            MileageTypeSeeder::class,
            ImportTypeSeeder::class,
            FuelTypeSeeder::class,
            TransmissionSeeder::class,
            ExteriorColorSeeder::class,
            DriveTypeSeeder::class,
            WarrantyStatusSeeder::class,
            CylinderOptionSeeder::class,
        ]);
    }
}
