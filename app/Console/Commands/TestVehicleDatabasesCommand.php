<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VehicleDatabasesService;
use App\Exceptions\VehicleDatabasesException;

class TestVehicleDatabasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vin:test {vin? : VIN to test (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Vehicle Databases API integration';

    /**
     * Execute the console command.
     */
    public function handle(VehicleDatabasesService $vehicleDatabasesService)
    {
        $this->info('Testing Vehicle Databases API Integration...');
        $this->newLine();

        // Test service configuration
        $this->info('1. Checking service configuration...');
        $status = $vehicleDatabasesService->getStatus();
        
        if ($status['configured']) {
            $this->info('✅ Service is properly configured');
            $this->line("   API URL: {$status['api_url']}");
            $this->line("   Timeout: {$status['timeout']} seconds");
        } else {
            $this->error('❌ Service is not configured');
            $this->line('   Please set VEHICLEDATABASES_API_KEY in your .env file');
            return 1;
        }

        $this->newLine();

        // Test VIN validation
        $this->info('2. Testing VIN validation...');
        $testVins = [
            '1HGBH41JXMN109186', // Valid Honda Civic
            '1FTFW1ET5DFC12345', // Valid Ford F-150
            'INVALID123456789',  // Invalid (contains I)
            '12345678901234567', // Invalid (too long)
            '1234567890123456',  // Invalid (too short)
        ];

        foreach ($testVins as $vin) {
            try {
                $vehicleDatabasesService->decodeVin($vin, false); // Don't cache for testing
                $this->info("✅ VIN {$vin} - Valid format");
            } catch (VehicleDatabasesException $e) {
                if (strpos($e->getMessage(), 'Invalid VIN format') !== false) {
                    $this->warn("⚠️  VIN {$vin} - Invalid format (expected)");
                } else {
                    $this->error("❌ VIN {$vin} - API Error: {$e->getMessage()}");
                }
            } catch (\Exception $e) {
                $this->error("❌ VIN {$vin} - Unexpected error: {$e->getMessage()}");
            }
        }

        $this->newLine();

        // Test with provided VIN or default
        $vin = $this->argument('vin') ?? '1HGBH41JXMN109186';
        
        if ($vin) {
            $this->info("3. Testing VIN decode for: {$vin}");
            
            try {
                $result = $vehicleDatabasesService->decodeVin($vin, false);
                
                if ($result['success']) {
                    $this->info('✅ VIN decoded successfully');
                    $data = $result['data'];
                    
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['VIN', $data['vin'] ?? 'N/A'],
                            ['Make', $data['make'] ?? 'N/A'],
                            ['Model', $data['model'] ?? 'N/A'],
                            ['Year', $data['year'] ?? 'N/A'],
                            ['Trim', $data['trim'] ?? 'N/A'],
                            ['Body Type', $data['body_type'] ?? 'N/A'],
                            ['Engine', $data['engine_type'] ?? 'N/A'],
                            ['Transmission', $data['transmission'] ?? 'N/A'],
                            ['Drive Type', $data['drive_type'] ?? 'N/A'],
                            ['Fuel Type', $data['fuel_type'] ?? 'N/A'],
                        ]
                    );
                } else {
                    $this->error('❌ VIN decode failed');
                }
            } catch (VehicleDatabasesException $e) {
                $this->error("❌ VIN decode failed: {$e->getMessage()}");
                $this->line("   Status Code: {$e->getStatusCode()}");
            } catch (\Exception $e) {
                $this->error("❌ Unexpected error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Vehicle Databases API test completed!');
        
        return 0;
    }
}

