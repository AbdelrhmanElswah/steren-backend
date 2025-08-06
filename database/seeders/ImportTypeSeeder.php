<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ImportType;

class ImportTypeSeeder extends Seeder
{
    public function run(): void
    {
        ImportType::insert([
            ['name' => 'GCC'],
            ['name' => 'American'],
            ['name' => 'Canadian'],
            ['name' => 'European'],
            ['name' => 'Japanese'],
            ['name' => 'Korean'],
            ['name' => 'Chinese'],
            ['name' => 'Others'],
        ]);
    }
}
