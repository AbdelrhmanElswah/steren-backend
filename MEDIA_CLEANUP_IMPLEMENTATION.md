# Media Cleanup Implementation

## Overview

This document describes the media cleanup and cascade delete logic that has been implemented to automatically clean up media files from S3 when records are deleted or updated.

---

## Implementation Summary

### ✅ What Was Implemented

1. **MediaObserver** - Automatically deletes S3 files when Media records are deleted
2. **Model Observers** - Cascade delete media records when parent models are deleted
3. **Update Cleanup** - Improved existing update logic to properly delete old media

### ✅ Models Covered

- ✅ **User** - Profile picture cleanup
- ✅ **CarListing** - All media cleanup
- ✅ **Carousel** - All media cleanup
- ✅ **CarBrand** - Logo cleanup
- ✅ **BodyType** - All media cleanup
- ✅ **AuthorizedAgency** - Media cleanup
- ✅ **CarSubmission** - All media cleanup

---

## How It Works

### 1. MediaObserver (Core Cleanup)

**Location**: `app/Observers/MediaObserver.php`

**Purpose**: Automatically deletes S3 files when Media records are deleted.

**How it works**:
- Listens to the `deleting` event on Media model
- When a Media record is deleted, it deletes the corresponding S3 file
- Logs success/failure for debugging
- Does NOT prevent Media record deletion if S3 deletion fails (ensures database consistency)

**Key Features**:
- Checks if file exists before attempting deletion
- Handles errors gracefully (logs but doesn't fail)
- Works for both soft delete and hard delete

### 2. Model Observers (Cascade Delete)

Each model with media relationships has an observer that:
- Listens to `deleting` event (soft delete)
- Listens to `forceDeleting` event (hard delete)
- Deletes all associated media records
- MediaObserver automatically handles S3 file deletion

**Observers Created**:
- `CarListingObserver` - Updated to handle media cleanup
- `CarouselObserver` - New
- `CarBrandObserver` - New
- `BodyTypeObserver` - New
- `AuthorizedAgencyObserver` - New
- `CarSubmissionObserver` - New
- `UserObserver` - New (for profile picture)

### 3. Update Cleanup

**CarBrandService** (`app/Services/Odoo/CarBrandService.php`):
- When updating brand logo, old logo is deleted before uploading new one
- MediaObserver handles S3 file deletion

**CarouselService** (`app/Services/Odoo/CarouselService.php`):
- When updating carousel image, old images are deleted before uploading new one
- MediaObserver handles S3 file deletion

**UserProfileController** (`app/Http/Controllers/Auth/UserProfileController.php`):
- Already had cleanup logic for profile picture updates
- Now uses MediaObserver for S3 deletion (via delete() call)

---

## Event Flow

### When a Model is Deleted:

```
1. Model::delete() or Model::forceDelete() is called
2. Model Observer::deleting() or forceDeleting() fires
3. Observer deletes all associated Media records
4. For each Media record deleted:
   - MediaObserver::deleting() fires
   - S3 file is deleted
   - Media record is removed from database
5. Model is deleted/soft deleted
```

### When Media is Updated:

```
1. Service/Controller detects media update
2. Old Media record is deleted
3. MediaObserver::deleting() fires
4. S3 file is deleted
5. New Media record is created
6. New S3 file is uploaded
```

---

## Safety Features

### ✅ Existing Records Protected

- **No impact on existing records**: The cleanup logic only runs when records are deleted or updated
- **Existing media files remain untouched** until their parent record is deleted or media is updated
- **No migration needed**: All logic is event-driven and works with existing data

### ✅ Error Handling

- **Graceful degradation**: If S3 deletion fails, the Media record deletion still proceeds
- **Logging**: All operations are logged for debugging
- **No data loss**: Database operations complete even if S3 operations fail

### ✅ Soft Delete Support

- **Soft delete**: Media records are soft deleted (if model supports it), triggering cleanup
- **Hard delete**: Media records are force deleted, triggering cleanup
- **Restore**: If parent model is restored, media records are NOT automatically restored (by design)

---

## Registration

All observers are registered in `app/Providers/AppServiceProvider.php`:

```php
public function boot(): void
{
    Media::observe(MediaObserver::class);
    CarListing::observe(CarListingObserver::class);
    Carousel::observe(CarouselObserver::class);
    CarBrand::observe(CarBrandObserver::class);
    BodyType::observe(BodyTypeObserver::class);
    AuthorizedAgency::observe(AuthorizedAgencyObserver::class);
    CarSubmission::observe(CarSubmissionObserver::class);
    User::observe(UserObserver::class);
}
```

---

## Testing Recommendations

### Test Scenarios:

1. **Soft Delete**:
   - Soft delete a CarListing
   - Verify media records are deleted
   - Verify S3 files are deleted

2. **Hard Delete**:
   - Force delete a CarBrand
   - Verify logo media record is deleted
   - Verify S3 file is deleted

3. **Update**:
   - Update a CarBrand logo
   - Verify old logo S3 file is deleted
   - Verify new logo is uploaded

4. **Error Handling**:
   - Simulate S3 deletion failure
   - Verify Media record is still deleted
   - Verify error is logged

---

## Files Modified/Created

### Created:
- `app/Observers/MediaObserver.php`
- `app/Observers/CarouselObserver.php`
- `app/Observers/CarBrandObserver.php`
- `app/Observers/BodyTypeObserver.php`
- `app/Observers/AuthorizedAgencyObserver.php`
- `app/Observers/CarSubmissionObserver.php`
- `app/Observers/UserObserver.php`

### Modified:
- `app/Observers/CarListingObserver.php` - Added media cleanup
- `app/Providers/AppServiceProvider.php` - Registered all observers
- `app/Services/Odoo/CarBrandService.php` - Improved media deletion
- `app/Services/Odoo/CarouselService.php` - Improved media deletion

---

## Benefits

1. **Automatic Cleanup**: No manual intervention needed
2. **Storage Cost Reduction**: Orphaned files are automatically deleted
3. **Data Integrity**: Media records are cleaned up when parent records are deleted
4. **Consistent Behavior**: All models with media follow the same cleanup pattern
5. **Safe**: Existing records are not affected
6. **Maintainable**: Centralized cleanup logic in observers

---

## Notes

- **NewsArticle**: Uses `mediable` instead of `modelable` for relationship. Observer not created yet (can be added if needed).
- **Restore Behavior**: When a soft-deleted model is restored, media records are NOT automatically restored. This is by design to prevent orphaned media.
- **Performance**: Media deletion happens synchronously. For large-scale deletions, consider moving to a queue job.

---

## Future Enhancements

1. **Queue Jobs**: Move S3 deletion to background jobs for better performance
2. **Batch Cleanup**: Add Artisan command to clean up orphaned media files
3. **Restore Support**: Add logic to restore media when parent model is restored
4. **NewsArticle Observer**: Add observer for NewsArticle if needed

