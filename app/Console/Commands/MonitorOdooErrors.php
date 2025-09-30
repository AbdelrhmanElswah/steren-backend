<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MonitorOdooErrors extends Command
{
    protected $signature = 'odoo:monitor-errors {--hours=24 : Number of hours to analyze}';
    protected $description = 'Monitor and analyze Odoo API errors from logs';

    public function handle()
    {
        $hours = $this->option('hours');
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            $this->error('Log file not found: ' . $logPath);
            return 1;
        }

        $this->info("Analyzing Odoo errors from the last {$hours} hours...");
        
        $errors = $this->analyzeLogs($logPath, $hours);
        
        if (empty($errors)) {
            $this->info('No Odoo errors found in the specified time period.');
            return 0;
        }

        $this->displayErrorSummary($errors);
        
        return 0;
    }

    private function analyzeLogs(string $logPath, int $hours): array
    {
        $cutoffTime = now()->subHours($hours);
        $errors = [];
        
        $lines = File::lines($logPath);
        
        foreach ($lines as $line) {
            if (str_contains($line, 'Odoo') && str_contains($line, 'ERROR')) {
                $timestamp = $this->extractTimestamp($line);
                
                if ($timestamp && $timestamp->gte($cutoffTime)) {
                    $errors[] = [
                        'timestamp' => $timestamp,
                        'line' => $line,
                        'type' => $this->extractErrorType($line),
                    ];
                }
            }
        }
        
        return $errors;
    }

    private function extractTimestamp(string $line): ?\Carbon\Carbon
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return \Carbon\Carbon::parse($matches[1]);
        }
        
        return null;
    }

    private function extractErrorType(string $line): string
    {
        if (str_contains($line, 'OdooConnectionException')) return 'Connection Error';
        if (str_contains($line, 'OdooValidationException')) return 'Validation Error';
        if (str_contains($line, 'OdooModelNotFoundException')) return 'Model Not Found';
        if (str_contains($line, 'OdooDatabaseException')) return 'Database Error';
        if (str_contains($line, 'OdooServiceException')) return 'Service Error';
        
        return 'Unknown Error';
    }

    private function displayErrorSummary(array $errors): void
    {
        $this->info('Odoo Error Summary:');
        $this->line('==================');
        
        $errorTypes = array_count_values(array_column($errors, 'type'));
        
        foreach ($errorTypes as $type => $count) {
            $this->line("{$type}: {$count} occurrences");
        }
        
        $this->line('');
        $this->info('Recent Errors:');
        $this->line('==============');
        
        $recentErrors = array_slice($errors, -10);
        
        foreach ($recentErrors as $error) {
            $this->line("[{$error['timestamp']->format('Y-m-d H:i:s')}] {$error['type']}");
        }
    }
}
