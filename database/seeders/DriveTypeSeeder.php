<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DriveType;

class DriveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $driveTypes = [
            ['name' => 'RWD'],
            ['name' => 'FWD'],
            ['name' => 'AWD'],
            ['name' => '4x4'],
        ];

        foreach ($driveTypes as $type) {
            DriveType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
