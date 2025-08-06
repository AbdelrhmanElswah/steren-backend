<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExteriorColor;

class ExteriorColorSeeder extends Seeder
{
    public function run(): void
    {
        ExteriorColor::insert([
            ['name' => 'White'],
            ['name' => 'Black'],
            ['name' => 'Red'],
            ['name' => 'Grey'],
            ['name' => 'Blue'],
            ['name' => 'Green'],
            ['name' => 'Purple'],
            ['name' => 'Other'],
        ]);
    }
}
