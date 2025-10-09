<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use Illuminate\Support\Facades\Log;

class MigrateMediaToS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-to-s3 {--dry-run : Show what would be migrated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate media files from local storage to S3';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No files will be actually migrated');
        } else {
            $this->info('🚀 Migrating media files to S3...');
        }
        
        try {
            $this->migrateMediaFiles($isDryRun);
            $this->info('✅ Media migration completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Media migration failed: ' . $e->getMessage());
            Log::error('Media migration failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        return 0;
    }

    private function migrateMediaFiles(bool $isDryRun)
    {
        $mediaRecords = Media::whereNotNull('path_file_name')->get();
        
        $this->info('Found ' . $mediaRecords->count() . ' media records to process');
        
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        
        foreach ($mediaRecords as $media) {
            $this->processMediaRecord($media, $isDryRun, $successCount, $errorCount, $skippedCount);
        }
        
        $this->info("\n📊 Migration Summary:");
        $this->info("✅ Successfully migrated: {$successCount}");
        $this->info("❌ Errors: {$errorCount}");
        $this->info("⏭️  Skipped: {$skippedCount}");
    }

    private function processMediaRecord(Media $media, bool $isDryRun, int &$successCount, int &$errorCount, int &$skippedCount)
    {
        $localPath = $media->path_file_name;
        
        // Check if file exists locally
        if (!Storage::disk('public')->exists($localPath)) {
            $this->warn("⚠️  Local file not found: {$localPath}");
            $skippedCount++;
            return;
        }
        
        // Check if file already exists in S3
        if (Storage::disk('s3')->exists($localPath)) {
            $this->info("⏭️  File already exists in S3: {$localPath}");
            $skippedCount++;
            return;
        }
        
        try {
            if ($isDryRun) {
                $this->line("🔍 Would migrate: {$localPath}");
                $successCount++;
            } else {
                // Read file from local storage
                $fileContent = Storage::disk('public')->get($localPath);
                
                // Upload to S3
                Storage::disk('s3')->put($localPath, $fileContent);
                
                $this->info("✅ Migrated: {$localPath}");
                $successCount++;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to migrate {$localPath}: " . $e->getMessage());
            $errorCount++;
            
            Log::error('Media migration error', [
                'media_id' => $media->id,
                'path' => $localPath,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
