<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use App\Traits\MediaTrait;

class TestS3Connection extends Command
{
    use MediaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-s3-connection {--test-media : Test with existing media records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test S3 connection and media trait functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing S3 Connection...');
        
        try {
            // Test basic S3 connection
            $this->testBasicConnection();
            
            // Test media trait if requested
            if ($this->option('test-media')) {
                $this->testMediaTrait();
            }
            
            $this->info('✅ S3 connection test completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ S3 connection test failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    private function testBasicConnection()
    {
        $this->info('Testing basic S3 connection...');
        
        // Test disk configuration
        $disk = Storage::disk('s3');
        $this->info('S3 Disk configured: ' . ($disk ? 'Yes' : 'No'));
        
        // Test bucket access
        try {
            $files = $disk->files('', true);
            $this->info('✅ Successfully connected to S3 bucket');
            $this->info('Total files in bucket: ' . count($files));
            
            if (count($files) > 0) {
                $this->info('Sample files:');
                foreach (array_slice($files, 0, 5) as $file) {
                    $this->line('  - ' . $file);
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to access S3 bucket: ' . $e->getMessage());
            throw $e;
        }
    }

    private function testMediaTrait()
    {
        $this->info('Testing Media trait functionality...');
        
        // Get a sample media record
        $media = Media::first();
        
        if (!$media) {
            $this->warn('No media records found. Skipping media trait test.');
            return;
        }
        
        $this->info('Testing with media ID: ' . $media->id);
        $this->info('Media path: ' . $media->path_file_name);
        
        // Test file existence
        $exists = $this->fileExistsInS3($media->path_file_name);
        $this->info('File exists in S3: ' . ($exists ? 'Yes' : 'No'));
        
        if ($exists) {
            // Test temporary URL
            $tempUrl = $this->getS3TemporaryUrl($media->path_file_name, 60);
            $this->info('Temporary URL generated: ' . ($tempUrl ? 'Yes' : 'No'));
            
            if ($tempUrl) {
                $this->info('Temporary URL: ' . $tempUrl);
            }
            
            // Test public URL
            $publicUrl = $this->getS3PublicUrl($media->path_file_name);
            $this->info('Public URL generated: ' . ($publicUrl ? 'Yes' : 'No'));
            
            if ($publicUrl) {
                $this->info('Public URL: ' . $publicUrl);
            }
            
            // Test file size
            $fileSize = $this->getS3FileSize($media->path_file_name);
            $this->info('File size: ' . ($fileSize ? $fileSize . ' bytes' : 'Unknown'));
            
            // Test MIME type
            $mimeType = $this->getS3FileMimeType($media->path_file_name);
            $this->info('MIME type: ' . ($mimeType ?: 'Unknown'));
        }
    }
}
