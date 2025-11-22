<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transmission;

class TransmissionSeeder extends Seeder
{
    public function run(): void
    {
        $transmissions = [
            ['name' => 'Automatic'],
            ['name' => 'Manual'],
        ];

        foreach ($transmissions as $transmission) {
            Transmission::updateOrCreate(
                ['name' => $transmission['name']],
                $transmission
            );
        }
    }
}
