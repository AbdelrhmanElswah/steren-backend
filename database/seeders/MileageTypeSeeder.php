<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MileageType;

class MileageTypeSeeder extends Seeder
{
    public function run(): void
    {
        MileageType::insert([
            ['name' => 'KM'],
            ['name' => 'Mile'],
        ]);
    }
}

