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
                'value' => 'new',
                'description' => 'Brand new vehicles',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Used',
                'value' => 'used',
                'description' => 'Previously owned vehicles',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($conditionTypes as $condition) {
            CarCondition::create($condition);
        }
    }
}
