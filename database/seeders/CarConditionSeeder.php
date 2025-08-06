<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarCondition;

class CarConditionSeeder extends Seeder
{
    public function run(): void
    {
        CarCondition::insert([
            ['name' => 'New'],
            ['name' => 'Used'],
        ]);
    }
}

