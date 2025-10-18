<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\DocumentTrait;
use App\Traits\HandlesFileUpload;

class Document extends Model
{
    use HasFactory, SoftDeletes, DocumentTrait, HandlesFileUpload;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'name',
        'path_file_name',
        'file_path',
        'url',
        'file_name',
        'file_type',
        'mime_type',
        'file_size',
        'document_type',
        'category',
        'description',
        'is_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $appends = [
        'document_url',
        'document_temporary_url',
        'formatted_file_size',
        'file_exists',
        'download_url',
    ];

    /**
     * Get the parent documentable model (polymorphic relationship)
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who verified this document
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get document URL (temporary URL)
     */
    public function getDocumentUrlAttribute()
    {
        return $this->getS3TemporaryUrl($this->path_file_name);
    }

    /**
     * Get document temporary URL with custom expiration
     */
    public function getDocumentTemporaryUrlAttribute()
    {
        return $this->getS3TemporaryUrl($this->path_file_name, 60); // 1 hour expiration
    }

    /**
     * Get download URL (longer expiration for downloads)
     */
    public function getDownloadUrlAttribute()
    {
        return $this->getS3TemporaryUrl($this->path_file_name, 1440); // 24 hours expiration
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute()
    {
        return $this->formatFileSize($this->file_size);
    }

    /**
     * Check if file exists in S3
     */
    public function getFileExistsAttribute()
    {
        return $this->fileExistsInS3($this->path_file_name);
    }

    /**
     * Get file extension
     */
    public function getFileExtensionAttribute()
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get human readable file type
     */
    public function getFileTypeLabelAttribute()
    {
        return match ($this->file_type) {
            'pdf' => 'PDF Document',
            'doc', 'docx' => 'Word Document',
            'xls', 'xlsx' => 'Excel Spreadsheet',
            'ppt', 'pptx' => 'PowerPoint Presentation',
            'txt' => 'Text Document',
            'jpg', 'jpeg', 'png', 'gif' => 'Image',
            default => strtoupper($this->file_type) . ' File',
        };
    }

    /**
     * Get verification status label
     */
    public function getVerificationStatusAttribute()
    {
        if ($this->is_verified) {
            return 'Verified';
        }
        
        return 'Pending Verification';
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for pending verification
     */
    public function scopePendingVerification($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by file type
     */
    public function scopeByFileType($query, $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified($userId, $notes = null)
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => $userId,
            'verified_at' => now(),
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Mark document as unverified
     */
    public function markAsUnverified($notes = null)
    {
        $this->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
