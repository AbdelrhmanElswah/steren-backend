<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CylinderOption;

class CylinderOptionSeeder extends Seeder
{
    public function run(): void
    {
        CylinderOption::insert([
            ['name' => '3'],
            ['name' => '4'],
            ['name' => '5'],
            ['name' => '6'],
            ['name' => '8'],
            ['name' => '10'],
            ['name' => 'Unknown'],
            ['name' => 'Electric'],
        ]);
    }
}
