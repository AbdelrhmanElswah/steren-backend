<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MileageType;

class MileageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $mileageTypes = [
            ['name' => 'KM'],
            ['name' => 'Mile'],
        ];

        foreach ($mileageTypes as $type) {
            MileageType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}

