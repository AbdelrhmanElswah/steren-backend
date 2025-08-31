<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarTrim;
use App\Models\CarModel;

class CarTrimSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Ford Taurus model (assuming it exists)
        $fordTaurus = CarModel::where('name', 'Taurus')->first();
        
        if ($fordTaurus) {
            $trims = [
                [
                    'name' => '4.0L EXR',
                    'base_price' => 56700.00,
                    'engine_size' => '4.0L',
                    'engine_type' => 'V6',
                    'horsepower' => 326,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 1,
                ],
                [
                    'name' => '4.0L GXR',
                    'base_price' => 70800.00,
                    'engine_size' => '4.0L',
                    'engine_type' => 'V6',
                    'horsepower' => 326,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 2,
                ],
                [
                    'name' => '4.0L VXR',
                    'base_price' => 76200.00,
                    'engine_size' => '4.0L',
                    'engine_type' => 'V6',
                    'horsepower' => 326,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 3,
                ],
                [
                    'name' => '3.5T GXR',
                    'base_price' => 81500.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 4,
                ],
                [
                    'name' => '3.5T VXR',
                    'base_price' => 87300.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 5,
                ],
                [
                    'name' => '3.5T GR Sport',
                    'base_price' => 95000.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 6,
                ],
                [
                    'name' => '3.3D VX (Diesel)',
                    'base_price' => 82000.00,
                    'engine_size' => '3.3L',
                    'engine_type' => 'Diesel',
                    'horsepower' => 350,
                    'torque' => '700 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 7.2,
                    'top_speed' => 200,
                    'seating_capacity' => 7,
                    'sort_order' => 7,
                ],
                [
                    'name' => '3.5T Black Edition',
                    'base_price' => 90000.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 8,
                ],
                [
                    'name' => '3.5T Adventure Edition',
                    'base_price' => 92500.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 9,
                ],
                [
                    'name' => '3.5T 10th Anniversary',
                    'base_price' => 98000.00,
                    'engine_size' => '3.5L',
                    'engine_type' => 'V6 Twin-Turbo',
                    'horsepower' => 409,
                    'torque' => '650 Nm',
                    'transmission' => '10-Speed Automatic',
                    'cylinders' => 6,
                    'drive_type' => 'Four-Wheel Drive',
                    'acceleration_0_100' => 6.8,
                    'top_speed' => 210,
                    'seating_capacity' => 7,
                    'sort_order' => 10,
                ],
            ];

            foreach ($trims as $trim) {
                CarTrim::create(array_merge($trim, ['car_model_id' => $fordTaurus->id]));
            }
        }
    }
}
