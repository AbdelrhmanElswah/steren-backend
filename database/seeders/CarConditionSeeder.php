<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarCondition;

class CarConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            ['name' => 'New'],
            ['name' => 'Used'],
        ];

        foreach ($conditions as $condition) {
            CarCondition::updateOrCreate(
                ['name' => $condition['name']],
                $condition
            );
        }
    }
}

