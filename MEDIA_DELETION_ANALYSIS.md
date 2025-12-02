# Media Deletion Analysis

## Summary

This document analyzes what happens to media files when records with media relationships are deleted (soft or hard) or when media is updated.

---

## Models with Media Relationships

The following models have media relationships:

1. **User** - `profilePictureMedia()` (morphOne, info: 'profile_picture')
2. **CarListing** - `media()` (morphMany)
3. **Carousel** - `media()` (morphOne, info: 'main'), `medias()` (morphMany)
4. **CarBrand** - `media()` (morphOne, info: 'logo')
5. **BodyType** - `media()` (morphOne, info: 'main'), `medias()` (morphMany)
6. **AuthorizedAgency** - `media()` (morphOne, info: 'authorized_agency_media')
7. **CarSubmission** - `media()` (morphMany)
8. **NewsArticle** - `media()` (morphMany, note: uses 'mediable' instead of 'modelable')

---

## Current Media Cleanup Behavior

### ✅ User Profile Picture (HAS CLEANUP)

**Location**: `app/Http/Controllers/Auth/UserProfileController.php`

**When Updated**:
- ✅ Old profile picture is deleted from S3
- ✅ Old media record is deleted from database
- ✅ New profile picture is uploaded and new media record is created

**Method**: `deleteOldProfilePicture(User $user)`
```php
// Deletes from S3
Storage::disk('s3')->delete($existingMedia->path_file_name);

// Deletes media record
Media::where('modelable_id', $user->id)
    ->where('modelable_type', get_class($user))
    ->where('info', 'profile_picture')
    ->delete();
```

**When User is Soft Deleted**: ❌ **NO CLEANUP** - Media files remain in S3 and media records remain in database

**When User is Hard Deleted**: ❌ **NO CLEANUP** - Media files remain in S3 and media records remain in database

---

### ❌ CarListing (NO CLEANUP)

**Soft Delete**: ✅ Uses `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When a CarListing is soft deleted, media records remain in database
- Media files remain in S3
- No observer or boot method handles cleanup

**Media Cleanup on Update**: ❌ **NONE**
- When updating car listing images, old images are not deleted
- Old media records remain in database
- Old files remain in S3

**Observer**: `CarListingObserver` exists but only handles `created` and `updated` events, not `deleting` or `deleted`

---

### ❌ Carousel (NO CLEANUP)

**Soft Delete**: ✅ Uses `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When a Carousel is soft deleted, media records remain
- Media files remain in S3

**Media Cleanup on Update**: ❌ **NONE**
- When updating carousel image, old image is not deleted

---

### ❌ CarBrand (NO CLEANUP)

**Soft Delete**: ✅ Uses `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When a CarBrand is soft deleted, logo media record remains
- Logo file remains in S3

**Media Cleanup on Update**: ❌ **NONE**
- When updating brand logo, old logo is not deleted

---

### ❌ BodyType (NO CLEANUP)

**Soft Delete**: ❌ Does NOT use `SoftDeletes` trait (no soft delete support)
**Media Cleanup on Delete**: ❌ **NONE**
- When a BodyType is hard deleted, media records remain
- Media files remain in S3

**Media Cleanup on Update**: ❌ **NONE**

---

### ❌ AuthorizedAgency (NO CLEANUP)

**Soft Delete**: ✅ Uses `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When an AuthorizedAgency is soft deleted, media record remains
- Media file remains in S3

**Media Cleanup on Update**: ❌ **NONE**

---

### ❌ CarSubmission (NO CLEANUP)

**Soft Delete**: ✅ Uses `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When a CarSubmission is soft deleted, media records remain
- Media files remain in S3

**Media Cleanup on Update**: ❌ **NONE**

---

### ❌ NewsArticle (NO CLEANUP)

**Soft Delete**: ❌ Does NOT use `SoftDeletes` trait
**Media Cleanup on Delete**: ❌ **NONE**
- When a NewsArticle is hard deleted, media records remain
- Media files remain in S3

**Note**: Uses `mediable` instead of `modelable` for the relationship

---

## Media Model Behavior

**Location**: `app/Models/Media.php`

**Boot Method**: Empty - no automatic cleanup logic
```php
protected static function boot()
{
    parent::boot();
    // No deletion handlers
}
```

**When Media Record is Deleted**:
- ❌ S3 file is NOT automatically deleted
- Only the database record is removed
- File remains orphaned in S3

---

## Issues Identified

### 1. **Orphaned Media Files**
- When models are soft deleted or hard deleted, their associated media files remain in S3
- Media records may or may not remain depending on cascade settings (likely remain due to polymorphic relationship)

### 2. **Storage Costs**
- Orphaned files accumulate in S3, increasing storage costs
- No cleanup mechanism exists for most models

### 3. **Data Integrity**
- Media records may reference soft-deleted or hard-deleted parent records
- No foreign key constraints on polymorphic relationships

### 4. **Inconsistent Behavior**
- Only User profile pictures have cleanup logic
- All other models lack cleanup mechanisms

---

## Recommendations

### 1. **Add Model Observers**
Create observers for each model with media relationships to handle cleanup:

```php
// Example: CarListingObserver
public function deleting(CarListing $carListing): void
{
    // Delete all media files from S3
    foreach ($carListing->media as $media) {
        if ($media->path_file_name) {
            Storage::disk('s3')->delete($media->path_file_name);
        }
    }
    
    // Delete media records (will be handled by cascade or manually)
    $carListing->media()->delete();
}
```

### 2. **Add Media Model Observer**
Handle cleanup when Media record is deleted:

```php
// MediaObserver
public function deleting(Media $media): void
{
    if ($media->path_file_name) {
        Storage::disk('s3')->delete($media->path_file_name);
    }
}
```

### 3. **Add Update Cleanup**
For models that replace media (like CarBrand logo, Carousel image), delete old media when updating:

```php
// In controller or service
if ($request->hasFile('logo')) {
    // Delete old logo
    if ($carBrand->media) {
        Storage::disk('s3')->delete($carBrand->media->path_file_name);
        $carBrand->media->delete();
    }
    // Upload new logo
}
```

### 4. **Add Soft Delete Cleanup**
Handle cleanup on soft delete (if you want to keep files for restore):

```php
public function deleted(CarListing $carListing): void
{
    // Option 1: Keep files (for restore capability)
    // Do nothing
    
    // Option 2: Delete files immediately
    foreach ($carListing->media as $media) {
        if ($media->path_file_name) {
            Storage::disk('s3')->delete($media->path_file_name);
        }
    }
}
```

### 5. **Add Cleanup Command**
Create an Artisan command to clean up orphaned media files:

```php
php artisan media:cleanup-orphaned
```

This would:
- Find media records with deleted parent models
- Find S3 files without corresponding media records
- Optionally delete them

---

## Current State Summary

| Model | Soft Delete | Media Cleanup on Delete | Media Cleanup on Update |
|-------|-------------|------------------------|------------------------|
| User | ✅ | ❌ | ✅ (profile picture only) |
| CarListing | ✅ | ❌ | ❌ |
| Carousel | ✅ | ❌ | ❌ |
| CarBrand | ✅ | ❌ | ❌ |
| BodyType | ❌ | ❌ | ❌ |
| AuthorizedAgency | ✅ | ❌ | ❌ |
| CarSubmission | ✅ | ❌ | ❌ |
| NewsArticle | ❌ | ❌ | ❌ |

**Legend**:
- ✅ = Implemented
- ❌ = Not Implemented

---

## Conclusion

**Critical Issue**: Most models with media relationships do NOT clean up their media files when:
1. The parent record is soft deleted
2. The parent record is hard deleted
3. Media is updated/replaced

This leads to:
- Orphaned files in S3 (increasing storage costs)
- Orphaned media records in database
- Potential data integrity issues

**Immediate Action Required**: Implement cleanup mechanisms for all models with media relationships.


