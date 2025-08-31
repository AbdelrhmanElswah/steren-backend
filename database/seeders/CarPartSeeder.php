<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarPart;

class CarPartSeeder extends Seeder
{
    public function run(): void
    {
        $parts = [
            ['name' => 'front_bumper', 'display_name' => 'Front Bumper'],
            ['name' => 'rear_bumper', 'display_name' => 'Rear Bumper'],
            ['name' => 'hood', 'display_name' => 'Hood'],
            ['name' => 'roof', 'display_name' => 'Roof'],
            ['name' => 'left_door_front', 'display_name' => 'Left Front Door'],
            ['name' => 'left_door_rear', 'display_name' => 'Left Rear Door'],
            ['name' => 'right_door_front', 'display_name' => 'Right Front Door'],
            ['name' => 'right_door_rear', 'display_name' => 'Right Rear Door'],
            ['name' => 'left_fender', 'display_name' => 'Left Fender'],
            ['name' => 'right_fender', 'display_name' => 'Right Fender'],
        ];

        foreach ($parts as $part) {
            CarPart::create($part);
        }
    }
}
