<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExteriorColor;

class ExteriorColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'White'],
            ['name' => 'Black'],
            ['name' => 'Red'],
            ['name' => 'Grey'],
            ['name' => 'Blue'],
            ['name' => 'Green'],
            ['name' => 'Purple'],
            ['name' => 'Other'],
            ['name' => 'Gold'],
            ['name' => 'Turquoise'],
            ['name' => 'Silver'],
            ['name' => 'Beige'],
            ['name' => 'Brown'],
            ['name' => 'Bronze'],
            ['name' => 'Yellow'],
            ['name' => 'Orange'],
            ['name' => 'Burgundy'],
            ['name' => 'Maroon'],
            ['name' => 'Teal'],
            ['name' => 'Tan'],
            ['name' => 'Pink'],
        ];

        foreach ($colors as $color) {
            ExteriorColor::updateOrCreate(
                ['name' => $color['name']],
                $color
            );
        }
    }
}
