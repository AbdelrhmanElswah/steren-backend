<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transmission;

class TransmissionSeeder extends Seeder
{
    public function run(): void
    {
        Transmission::insert([
            ['name' => 'Automatic'],
            ['name' => 'Manual'],
        ]);
    }
}
