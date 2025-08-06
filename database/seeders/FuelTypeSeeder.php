<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FuelType;

class FuelTypeSeeder extends Seeder
{
    public function run(): void
    {
        FuelType::insert([
            ['name' => 'Petrol'],
            ['name' => 'Diesel'],
            ['name' => 'Electric'],
            ['name' => 'Hybrid'],
        ]);
    }
}
