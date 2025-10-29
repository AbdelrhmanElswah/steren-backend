<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CarBrand;

class AddCarBrandCommand extends Command
{
    protected $signature = 'brand:add {name} {--odoo-id=}';
    protected $description = 'Add a new car brand to the database';

    public function handle()
    {
        $name = $this->argument('name');
        $odooId = $this->option('odoo-id');

        // Check if brand already exists
        $existingBrand = CarBrand::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        
        if ($existingBrand) {
            $this->info("Brand '{$name}' already exists with ID: {$existingBrand->id}");
            return 0;
        }

        // Create new brand
        $brand = CarBrand::create([
            'name' => $name,
            'odoo_id' => $odooId
        ]);

        $this->info("Brand '{$name}' created successfully with ID: {$brand->id}");
        return 0;
    }
}
