<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarCondition;

class ConditionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conditionTypes = [
            [
                'name' => 'New',
            ],
            [
                'name' => 'Used',
            ],
        ];

        foreach ($conditionTypes as $condition) {
            CarCondition::updateOrCreate(
                ['name' => $condition['name']],
                $condition
            );
        }
    }
}
