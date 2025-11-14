<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait DocumentTrait
{
    /**
     * Get temporary S3 URL for a document
     *
     * @param string $path
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getS3TemporaryUrl(string $path, int $expirationMinutes = 60): ?string
    {
        try {
            if (empty($path)) {
                return null;
            }

            // Check if file exists in S3
            if (!Storage::disk('s3')->exists($path)) {
                Log::warning('DocumentTrait: File not found in S3', ['path' => $path]);
                return null;
            }

            // Generate temporary URL
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error generating S3 temporary URL', [
                'path' => $path,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get public S3 URL for a document
     * Optimized: Does not check file existence to avoid slow S3 API calls
     *
     * @param string $path
     * @return string|null
     */
    public function getS3PublicUrl(string $path): ?string
    {
        try {
            if (empty($path)) {
                return null;
            }

            // Generate public URL directly without checking existence (much faster)
            // The existence check was causing significant slowdown during cache warm-up
            // as it makes an S3 API call for each file
            return Storage::disk('s3')->url($path);
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error generating S3 public URL', [
                'path' => $path,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get document URL with fallback options
     *
     * @param string $path
     * @param bool $useTemporaryUrl
     * @param int $expirationMinutes
     * @return string|null
     */
    public function getDocumentUrl(string $path, bool $useTemporaryUrl = true, int $expirationMinutes = 60): ?string
    {
        if (empty($path)) {
            return null;
        }

        if ($useTemporaryUrl) {
            return $this->getS3TemporaryUrl($path, $expirationMinutes);
        }

        return $this->getS3PublicUrl($path);
    }

    /**
     * Check if file exists in S3
     *
     * @param string $path
     * @return bool
     */
    public function fileExistsInS3(string $path): bool
    {
        try {
            if (empty($path)) {
                return false;
            }

            return Storage::disk('s3')->exists($path);
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error checking file existence in S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file size from S3
     *
     * @param string $path
     * @return int|null
     */
    public function getS3FileSize(string $path): ?int
    {
        try {
            if (empty($path) || !$this->fileExistsInS3($path)) {
                return null;
            }

            return Storage::disk('s3')->size($path);
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error getting file size from S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get file MIME type from S3
     *
     * @param string $path
     * @return string|null
     */
    public function getS3FileMimeType(string $path): ?string
    {
        try {
            if (empty($path) || !$this->fileExistsInS3($path)) {
                return null;
            }

            return Storage::disk('s3')->mimeType($path);
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error getting file MIME type from S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store uploaded document to S3
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @param string|null $filename
     * @return string|null
     */
    public function storeDocumentToS3(\Illuminate\Http\UploadedFile $file, string $directory, ?string $filename = null): ?string
    {
        try {
            // Generate filename if not provided
            if (!$filename) {
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
            }

            // Clean filename
            $filename = $this->cleanFilename($filename);

            // Create full path
            $path = $directory . '/' . $filename;

            // Store file to S3
            $stored = Storage::disk('s3')->putFileAs($directory, $file, $filename, 'private');

            if ($stored) {
                return $path;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error storing document to S3', [
                'directory' => $directory,
                'filename' => $filename,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Delete document from S3
     *
     * @param string $path
     * @return bool
     */
    public function deleteDocumentFromS3(string $path): bool
    {
        try {
            if (empty($path)) {
                return false;
            }

            if ($this->fileExistsInS3($path)) {
                return Storage::disk('s3')->delete($path);
            }

            return true; // File doesn't exist, consider it deleted
        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error deleting document from S3', [
                'path' => $path,
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get document categories
     *
     * @return array
     */
    public function getDocumentCategories(): array
    {
        return [
            'business_license' => 'Business License',
            'authorization_letter' => 'Authorization Letter',
            'inspection_report' => 'Inspection Report',
            'insurance_document' => 'Insurance Document',
            'warranty_document' => 'Warranty Document',
            'carfax_report' => 'Carfax Report',
            'registration_document' => 'Registration Document',
            'ownership_document' => 'Ownership Document',
            'technical_specification' => 'Technical Specification',
            'maintenance_record' => 'Maintenance Record',
            'evidence' => 'Evidence',
            'other' => 'Other',
        ];
    }

    /**
     * Get allowed file types
     *
     * @return array
     */
    public function getAllowedFileTypes(): array
    {
        return [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
        ];
    }

    /**
     * Validate file type
     *
     * @param string $mimeType
     * @param string $extension
     * @return bool
     */
    public function isValidFileType(string $mimeType, string $extension): bool
    {
        $allowedTypes = $this->getAllowedFileTypes();
        
        if (!isset($allowedTypes[$extension])) {
            return false;
        }

        return in_array($mimeType, $allowedTypes[$extension]);
    }

    /**
     * Clean filename for safe storage
     *
     * @param string $filename
     * @return string
     */
    private function cleanFilename(string $filename): string
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Remove leading/trailing underscores
        $filename = trim($filename, '_');
        
        return $filename;
    }

    /**
     * Get file type from MIME type
     *
     * @param string $mimeType
     * @return string|null
     */
    public function getFileTypeFromMime(string $mimeType): ?string
    {
        $allowedTypes = $this->getAllowedFileTypes();
        
        foreach ($allowedTypes as $extension => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * Upload and store a document with automatic Document model creation
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $documentableType
     * @param int $documentableId
     * @param string|null $category
     * @param string|null $description
     * @return \App\Models\Document|null
     */
    public function uploadDocument(
        \Illuminate\Http\UploadedFile $file, 
        string $documentableType, 
        int $documentableId, 
        ?string $category = null, 
        ?string $description = null
    ): ?\App\Models\Document {
        try {
            // Validate file type
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            
            if (!$this->isValidFileType($mimeType, $extension)) {
                Log::warning('DocumentTrait: Invalid file type', [
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'filename' => $file->getClientOriginalName()
                ]);
                return null;
            }

            // Generate unique filename
            $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
            $filename = $this->cleanFilename($filename);
            
            // Create directory path based on documentable type
            $directory = 'documents/' . \Illuminate\Support\Str::snake(class_basename($documentableType)) . '/' . $documentableId;
            
            // Store file to S3
            $path = $this->storeDocumentToS3($file, $directory, $filename);

            if (!$path) {
                Log::error('DocumentTrait: Failed to store document to S3', [
                    'directory' => $directory,
                    'filename' => $filename
                ]);
                return null;
            }

            // Create document record
            $document = \App\Models\Document::create([
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'name' => $file->getClientOriginalName(),
                'path_file_name' => $path,
                'file_type' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'category' => $category,
                'description' => $description,
            ]);

            Log::info('DocumentTrait: Document uploaded successfully', [
                'document_id' => $document->id,
                'path' => $path,
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId
            ]);

            return $document;

        } catch (\Exception $e) {
            Log::error('DocumentTrait: Error uploading document', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filename' => $file->getClientOriginalName()
            ]);
            return null;
        }
    }

    /**
     * Upload multiple documents at once
     *
     * @param array $files
     * @param string $documentableType
     * @param int $documentableId
     * @param string|null $category
     * @param string|null $description
     * @return array
     */
    public function uploadMultipleDocuments(
        array $files, 
        string $documentableType, 
        int $documentableId, 
        ?string $category = null, 
        ?string $description = null
    ): array {
        $uploaded = [];
        $failed = [];

        foreach ($files as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $document = $this->uploadDocument($file, $documentableType, $documentableId, $category, $description);
                
                if ($document) {
                    $uploaded[] = $document;
                } else {
                    $failed[] = $file->getClientOriginalName();
                }
            }
        }

        return [
            'uploaded' => $uploaded,
            'failed' => $failed,
            'uploaded_count' => count($uploaded),
            'failed_count' => count($failed)
        ];
    }

    /**
     * Validate document upload request
     *
     * @param \Illuminate\Http\Request $request
     * @param string $fieldName
     * @return array
     */
    public function validateDocumentUpload(\Illuminate\Http\Request $request, string $fieldName = 'document'): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            $fieldName => 'required|file|max:10240', // 10MB max
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()
            ];
        }

        $file = $request->file($fieldName);
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (!$this->isValidFileType($mimeType, $extension)) {
            return [
                'valid' => false,
                'errors' => ['file' => ['File type not allowed. Allowed types: ' . implode(', ', array_keys($this->getAllowedFileTypes()))]]
            ];
        }

        return [
            'valid' => true,
            'file' => $file,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'category' => $request->input('category'),
            'description' => $request->input('description')
        ];
    }

    /**
     * Get document upload form data for API responses
     *
     * @param \App\Models\Document $document
     * @return array
     */
    public function formatDocumentResponse(\App\Models\Document $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->name,
            'file_type' => $document->file_type,
            'file_type_label' => $document->file_type_label,
            'file_size' => $document->formatted_file_size,
            'category' => $document->category,
            'description' => $document->description,
            'is_verified' => $document->is_verified,
            'verification_status' => $document->verification_status,
            'verified_by' => $document->verifier?->name,
            'verified_at' => $document->verified_at?->toISOString(),
            'verification_notes' => $document->verification_notes,
            'created_at' => $document->created_at->toISOString(),
            'updated_at' => $document->updated_at->toISOString(),
            'document_url' => $document->document_url,
            'download_url' => $document->download_url,
            'file_exists' => $document->file_exists,
        ];
    }
}
