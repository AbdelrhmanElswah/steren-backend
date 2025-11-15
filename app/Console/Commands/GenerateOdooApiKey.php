<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateOdooApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odoo:generate-key 
                            {--show : Show the generated key}
                            {--force : Force regenerate even if key exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure API key for Odoo cache clearing endpoint';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $envFile = base_path('.env');
        
        if (!file_exists($envFile)) {
            $this->error('.env file not found!');
            return 1;
        }

        $envContent = file_get_contents($envFile);
        
        // Check if ODOO_API_KEY already exists
        if (preg_match('/^ODOO_API_KEY=(.+)$/m', $envContent, $matches)) {
            $existingKey = trim($matches[1]);
            
            if (!$this->option('force')) {
                $this->warn('ODOO_API_KEY already exists in .env file!');
                $this->info('Current key: ' . $existingKey);
                $this->info('Use --force to regenerate a new key.');
                return 0;
            }
        }

        // Generate a secure random key (64 characters)
        $newKey = Str::random(64);
        
        // Update or add the key in .env file
        if (preg_match('/^ODOO_API_KEY=(.+)$/m', $envContent)) {
            // Replace existing key
            $envContent = preg_replace(
                '/^ODOO_API_KEY=(.+)$/m',
                'ODOO_API_KEY=' . $newKey,
                $envContent
            );
            $action = 'updated';
        } else {
            // Add new key at the end
            $envContent .= "\nODOO_API_KEY=" . $newKey . "\n";
            $action = 'added';
        }

        file_put_contents($envFile, $envContent);

        $this->info("✓ ODOO_API_KEY successfully {$action} in .env file!");
        
        if ($this->option('show')) {
            $this->line('');
            $this->line('Your Odoo API Key:');
            $this->line('<fg=green>' . $newKey . '</>');
        } else {
            $this->line('');
            $this->info('Key generated successfully! Use --show to display it.');
        }
        
        $this->line('');
        $this->comment('⚠️  Keep this key secret! Do not share it publicly.');
        $this->line('');
        $this->info('You can now use this key to call the cache/clear endpoint:');
        $this->line('POST /api/cache/clear');
        $this->line('Header: X-Odoo-API-Key: ' . $newKey);

        return 0;
    }
}

