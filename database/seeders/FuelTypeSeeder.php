<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FuelType;

class FuelTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fuelTypes = [
            ['name' => 'Petrol'],
            ['name' => 'Diesel'],
            ['name' => 'Electric'],
            ['name' => 'Hybrid'],
        ];

        foreach ($fuelTypes as $type) {
            FuelType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
