<?php

use Illuminate\Console\Command;
use App\Models\CarBrand;

class CheckBrandsCommand extends Command
{
    protected $signature = 'check:brands';
    protected $description = 'Check all brands in the database';

    public function handle()
    {
        $brands = CarBrand::all(['id', 'name']);
        
        $this->info('Current brands in database:');
        foreach ($brands as $brand) {
            $this->line($brand->id . ': ' . $brand->name);
        }
        
        return 0;
    }
}
