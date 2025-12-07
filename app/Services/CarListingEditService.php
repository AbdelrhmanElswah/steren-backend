<?php

namespace App\Services;

use App\Models\CarListing;
use App\Models\CarListingEdit;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CarListingEditService
{
    /**
     * Create a new car listing edit
     */
    public function create(array $data, bool $isFormData = false): CarListingEdit
    {
        DB::beginTransaction();
        
        try {
            $carListing = CarListing::findOrFail($data['car_listing_id']);
            
            // Store previous state
            $previousState = $carListing->status ?? 'pending';
            
            // Compute edit_type from previous_state
            $editType = $previousState === 'rejected' ? 'resubmission' : 'edit';
            
            // Get odoo_car_listing_id from the original car listing
            $odooCarListingId = $carListing->odoo_id;
            
            // Map request data to CarListingEdit structure
            if ($isFormData) {
                $editData = $this->mapFormDataToCarListingEditData($data, $carListing);
            } else {
                $editData = $this->mapToCarListingEditData($data, $carListing);
            }
            
            $editData['car_listing_id'] = $data['car_listing_id'];
            $editData['previous_state'] = $previousState;
            $editData['edit_type'] = $editType;
            $editData['odoo_car_listing_id'] = $odooCarListingId;
            $editData['status'] = 'pending';
            $editData['user_id'] = $carListing->user_id;
            
            // Create the edit record
            $carListingEdit = CarListingEdit::create($editData);
            
            // Note: Images are now handled separately in the controller using handleMixedImages
            // This allows us to process both existing URLs and new images together
            
            // Set original car listing to pending state
            $carListing->update(['status' => 'pending']);
            
            DB::commit();
            
            return $carListingEdit;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create car listing edit: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Map form data to CarListingEdit structure (for multipart/form-data)
     */
    public function mapFormDataToCarListingEditData(array $data, CarListing $carListing): array
    {
        $editData = [];
        
        // Map all form fields directly (they're already in the correct format)
        $fieldsToMap = [
            'vin', 'car_brand_id', 'car_model_id', 'condition_type', 'year', 'gcc_import',
            'car_body_type_id', 'engine_size', 'engine_unit', 'fuel_type_id', 'cylinder_id',
            'transmission_id', 'seating_capacity', 'exterior_color_id', 'has_warranty',
            'drive_type_id', 'mileage_type_id', 'mileage', 'plate_status', 'damaged_areas',
            'doors', 'trim', 'origin_country', 'is_inspected', 'is_verified', 'has_insurance',
            'city_id', 'import_type_id', 'warranty_status_id', 'engine_type', 'horsepower',
            'torque', 'acceleration_0_100', 'top_speed', 'mileage_efficiency', 'length',
            'width', 'height', 'forced_induction', 'status_id', 'operator_decision',
            'is_featured', 'is_new_arrival', 'description', 'features', 'title', 'price',
            'price_currency'
        ];
        
        foreach ($fieldsToMap as $field) {
            if (isset($data[$field])) {
                $editData[$field] = $data[$field];
            }
        }
        
        // Normalize gcc_import if provided
        if (isset($editData['gcc_import'])) {
            $editData['gcc_import'] = $this->normalizeGccImport($editData['gcc_import']);
        }
        
        return $editData;
    }
    
    /**
     * Map request data to CarListingEdit structure (for JSON with car_info structure)
     */
    private function mapToCarListingEditData(array $data, CarListing $carListing): array
    {
        $editData = [];
        
        // Map car_info if provided
        if (isset($data['car_info'])) {
            $carInfo = $data['car_info'];
            
            if (isset($carInfo['vin'])) {
                $editData['vin'] = substr($carInfo['vin'], 0, 17);
            }
            if (isset($carInfo['brand'])) {
                $editData['car_brand_id'] = $carInfo['brand'];
            }
            if (isset($carInfo['model'])) {
                $editData['car_model_id'] = $carInfo['model'];
            }
            if (isset($carInfo['condition'])) {
                $editData['condition_type'] = $carInfo['condition'];
            }
            if (isset($carInfo['year'])) {
                $editData['year'] = $carInfo['year'];
            }
            if (isset($carInfo['gcc_import'])) {
                $editData['gcc_import'] = $this->normalizeGccImport($carInfo['gcc_import']);
            }
            if (isset($carInfo['body_type'])) {
                $editData['car_body_type_id'] = $carInfo['body_type'];
            }
            if (isset($carInfo['engine_size'])) {
                $editData['engine_size'] = $carInfo['engine_size'];
            }
            if (isset($carInfo['fuel_type'])) {
                $editData['fuel_type_id'] = $this->getFuelTypeId($carInfo['fuel_type']);
            }
            if (isset($carInfo['cylinders'])) {
                $editData['cylinder_id'] = $this->getCylinderId($carInfo['cylinders']);
            }
            if (isset($carInfo['transmission'])) {
                $editData['transmission_id'] = $this->getTransmissionId($carInfo['transmission']);
            }
            if (isset($carInfo['seating_capacity'])) {
                $editData['seating_capacity'] = $carInfo['seating_capacity'];
            }
            if (isset($carInfo['exterior_colour'])) {
                $editData['exterior_color_id'] = $this->getExteriorColorId($carInfo['exterior_colour']);
            }
            if (isset($carInfo['warranty'])) {
                $editData['has_warranty'] = $carInfo['warranty'] === 'yes';
            }
            if (isset($carInfo['drive_type'])) {
                $editData['drive_type_id'] = $this->getDriveTypeId($carInfo['drive_type']);
            }
            if (isset($carInfo['mileage_type'])) {
                $editData['mileage_type_id'] = $this->getMileageTypeId($carInfo['mileage_type']);
            }
            if (isset($carInfo['mileage'])) {
                $editData['mileage'] = $carInfo['mileage'];
            }
            if (isset($carInfo['plate_status'])) {
                $editData['plate_status'] = $carInfo['plate_status'];
            }
            if (isset($carInfo['damaged_areas'])) {
                $editData['damaged_areas'] = $carInfo['damaged_areas'];
            }
        }
        
        // Map top-level fields
        if (isset($data['title'])) {
            $editData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $editData['description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $editData['price'] = $data['price'];
        }
        if (isset($data['price_currency'])) {
            $editData['price_currency'] = $data['price_currency'];
        }
        if (isset($data['city_id'])) {
            $editData['city_id'] = $data['city_id'];
        }
        
        return $editData;
    }
    
    /**
     * Compare CarListingEdit with original CarListing and return only changed fields
     * Only includes fields that are explicitly set in the edit (not null)
     */
    public function getChangedFields(CarListingEdit $edit, CarListing $original): array
    {
        $changedFields = [];
        
        // Compare all fields
        $fieldsToCompare = [
            'title', 'vin', 'price', 'price_currency', 'year', 'trim', 'doors',
            'mileage', 'origin_country', 'gcc_import', 'plate_status', 'condition_type',
            'has_warranty', 'is_inspected', 'is_verified', 'has_insurance',
            'city_id', 'studio_id', 'car_brand_id', 'car_body_type_id', 'car_model_id',
            'car_condition_id', 'mileage_type_id', 'import_type_id', 'fuel_type_id',
            'transmission_id', 'exterior_color_id', 'drive_type_id', 'warranty_status_id',
            'cylinder_id', 'engine_size', 'engine_unit', 'engine_type', 'horsepower',
            'torque', 'acceleration_0_100', 'top_speed', 'seating_capacity',
            'mileage_efficiency', 'length', 'width', 'height', 'forced_induction',
            'description', 'damaged_areas', 'features'
        ];
        
        foreach ($fieldsToCompare as $field) {
            // Only check fields that are explicitly set in the edit (not null)
            if ($edit->$field === null) {
                continue;
            }
            
            $editValue = $edit->$field;
            $originalValue = $original->$field;
            
            // Handle array comparison
            if (is_array($editValue) || is_array($originalValue)) {
                $editValue = is_array($editValue) ? $editValue : [];
                $originalValue = is_array($originalValue) ? $originalValue : [];
                if (json_encode($editValue) !== json_encode($originalValue)) {
                    $changedFields[$field] = $editValue;
                }
            } else {
                // Compare values (including null)
                if ($editValue !== $originalValue) {
                    $changedFields[$field] = $editValue;
                }
            }
        }
        
        return $changedFields;
    }
    
    /**
     * Prepare Odoo payload with only changed fields
     */
    public function prepareOdooEditPayload(CarListingEdit $edit, CarListing $original): array
    {
        $changedFields = $this->getChangedFields($edit, $original);
        
        // Get all images from edit (always send all images)
        $imageUrls = $edit->media()->orderBy('order')->get()->map(function ($media) {
            return $media->public_url ?? Storage::disk('s3')->url($media->path_file_name);
        })->toArray();
        
        // Build car_info with only changed fields
        $carInfo = [];
        
        if (isset($changedFields['vin'])) {
            $carInfo['vin'] = $changedFields['vin'];
        }
        if (isset($changedFields['car_brand_id'])) {
            $brand = \App\Models\CarBrand::find($changedFields['car_brand_id']);
            if ($brand && !empty($brand->odoo_id)) {
                $carInfo['brand'] = (int)$brand->odoo_id;
            }
        }
        if (isset($changedFields['car_model_id'])) {
            $model = \App\Models\CarModel::find($changedFields['car_model_id']);
            if ($model && !empty($model->odoo_id)) {
                $carInfo['model'] = (int)$model->odoo_id;
            }
        }
        if (isset($changedFields['condition_type'])) {
            $carInfo['condition'] = $changedFields['condition_type'];
        }
        if (isset($changedFields['year'])) {
            $carInfo['year'] = $changedFields['year'];
        }
        if (isset($changedFields['gcc_import'])) {
            $carInfo['gcc_import'] = $changedFields['gcc_import'];
        }
        if (isset($changedFields['car_body_type_id'])) {
            $bodyType = \App\Models\BodyType::find($changedFields['car_body_type_id']);
            if ($bodyType && !empty($bodyType->odoo_id)) {
                $carInfo['body_type'] = (int)$bodyType->odoo_id;
            }
        }
        if (isset($changedFields['engine_size'])) {
            $carInfo['engine_size'] = $changedFields['engine_size'];
        }
        if (isset($changedFields['fuel_type_id'])) {
            $carInfo['fuel_type'] = $this->getFuelTypeName($changedFields['fuel_type_id']);
        }
        if (isset($changedFields['cylinder_id'])) {
            $carInfo['cylinders'] = $this->getCylinderCount($changedFields['cylinder_id']);
        }
        if (isset($changedFields['transmission_id'])) {
            $carInfo['transmission'] = $this->getTransmissionName($changedFields['transmission_id']);
        }
        if (isset($changedFields['seating_capacity'])) {
            $carInfo['seating_capacity'] = $changedFields['seating_capacity'];
        }
        if (isset($changedFields['exterior_color_id'])) {
            $carInfo['exterior_colour'] = $this->getExteriorColorName($changedFields['exterior_color_id']);
        }
        if (isset($changedFields['has_warranty'])) {
            $carInfo['warranty'] = $changedFields['has_warranty'] ? 'yes' : 'no';
        }
        if (isset($changedFields['drive_type_id'])) {
            $carInfo['drive_type'] = $this->getDriveTypeName($changedFields['drive_type_id']);
        }
        if (isset($changedFields['mileage_type_id'])) {
            $carInfo['mileage_type'] = $this->getMileageTypeName($changedFields['mileage_type_id']);
        }
        if (isset($changedFields['mileage'])) {
            $carInfo['mileage'] = $changedFields['mileage'];
        }
        if (isset($changedFields['plate_status'])) {
            $carInfo['plate_status'] = $changedFields['plate_status'];
        }
        if (isset($changedFields['damaged_areas'])) {
            $carInfo['damaged_areas'] = $changedFields['damaged_areas'];
        }
        
        $payload = [
            'car_listing_id' => $edit->odoo_car_listing_id, // Send as car_listing_id to Odoo
            'images' => $imageUrls,
        ];
        
        if (!empty($carInfo)) {
            $payload['car_info'] = $carInfo;
        }
        
        if (isset($changedFields['price'])) {
            $payload['price'] = $changedFields['price'];
        }
        if (isset($changedFields['price_currency'])) {
            $payload['price_currency'] = $changedFields['price_currency'];
        }
        if (isset($changedFields['title'])) {
            $payload['title'] = $changedFields['title'];
        }
        if (isset($changedFields['description'])) {
            $payload['description'] = $changedFields['description'];
        }
        if (isset($changedFields['city_id'])) {
            $city = \App\Models\City::find($changedFields['city_id']);
            if ($city && $city->odoo_id) {
                $payload['city_id'] = (int)$city->odoo_id;
            }
        }
        
        return $payload;
    }
    
    /**
     * Approve edit and apply changes to original CarListing
     */
    public function approveEdit(CarListingEdit $edit): CarListing
    {
        DB::beginTransaction();
        
        try {
            $carListing = $edit->carListing;
            
            // Get all fields from edit (excluding edit-specific fields)
            $fieldsToUpdate = $edit->getFillable();
            $excludedFields = [
                'car_listing_id', 'odoo_id', 'odoo_car_listing_id',
                'previous_state', 'edit_type', 'status', 'approved_by', 'approved_at',
                'rejection_reason', 'rejected_by', 'rejected_at'
            ];
            
            $updateData = [];
            foreach ($fieldsToUpdate as $field) {
                if (!in_array($field, $excludedFields) && $edit->$field !== null) {
                    $updateData[$field] = $edit->$field;
                }
            }
            
            // Update car listing
            $carListing->update($updateData);
            
            // Replace images
            $this->replaceImages($carListing, $edit);
            
            // Set status based on edit_type
            if ($edit->edit_type === 'edit' || $edit->edit_type === 'resubmission') {
                $carListing->update(['status' => 'active']);
            }
            
            // Mark edit as approved
            $edit->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
            
            DB::commit();
            
            return $carListing;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve car listing edit: ' . $e->getMessage(), [
                'edit_id' => $edit->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Reject edit
     */
    public function rejectEdit(CarListingEdit $edit, ?string $reason = null): CarListing
    {
        DB::beginTransaction();
        
        try {
            $carListing = $edit->carListing;
            
            // Mark edit as rejected
            $edit->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ]);
            
            // Handle status based on edit_type
            if ($edit->edit_type === 'edit') {
                // Return to previous state (active or paused)
                $carListing->update(['status' => $edit->previous_state]);
            } else {
                // edit_type = resubmission: keep in rejected state
                $carListing->update(['status' => 'rejected']);
            }
            
            DB::commit();
            
            return $carListing;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject car listing edit: ' . $e->getMessage(), [
                'edit_id' => $edit->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Replace images from edit to original listing
     */
    private function replaceImages(CarListing $carListing, CarListingEdit $edit): void
    {
        // Delete old images
        $carListing->media()->delete();
        
        // Copy images from edit to listing
        $edit->media()->get()->each(function ($editMedia) use ($carListing) {
            Media::create([
                'modelable_type' => CarListing::class,
                'modelable_id' => $carListing->id,
                'media' => $editMedia->media,
                'path_file_name' => $editMedia->path_file_name,
                'file_type' => $editMedia->file_type,
                'info' => $editMedia->info,
                'order' => $editMedia->order ?? 0,
            ]);
        });
    }
    
    /**
     * Handle mixed images: existing URLs (to keep) and new binary files (to upload)
     * 
     * @param CarListingEdit $edit
     * @param CarListing $originalListing
     * @param array|null $existingImageUrls Array of URLs to keep from original listing
     * @param array|null $newImageFiles Array of binary files to upload (form-data)
     * @param array|null $newImageBase64 Array of base64 strings to upload (JSON)
     */
    public function handleMixedImages(
        CarListingEdit $edit, 
        CarListing $originalListing, 
        ?array $existingImageUrls = null,
        ?array $newImageFiles = null,
        ?array $newImageBase64 = null
    ): void {
        $order = 0;
        
        // Step 1: Handle existing images (URLs to keep)
        if (!empty($existingImageUrls)) {
            // Get original listing's media
            $originalMedia = $originalListing->media()->orderBy('order')->get();
            
            foreach ($existingImageUrls as $url) {
                // Find matching media record by URL
                $matchingMedia = $originalMedia->first(function ($media) use ($url) {
                    $mediaUrl = $media->public_url ?? Storage::disk('s3')->url($media->path_file_name);
                    return $mediaUrl === $url;
                });
                
                if ($matchingMedia) {
                    // Copy media record to edit
                    Media::create([
                        'modelable_type' => CarListingEdit::class,
                        'modelable_id' => $edit->id,
                        'media' => $matchingMedia->media,
                        'path_file_name' => $matchingMedia->path_file_name,
                        'file_type' => $matchingMedia->file_type,
                        'info' => $order === 0 ? 'primary' : 'secondary',
                        'order' => $order++,
                    ]);
                } else {
                    Log::warning('Could not find matching media for URL in car listing edit', [
                        'edit_id' => $edit->id,
                        'url' => $url,
                        'car_listing_id' => $originalListing->id
                    ]);
                }
            }
        }
        
        // Step 2: Handle new binary files (form-data)
        if (!empty($newImageFiles)) {
            foreach ($newImageFiles as $imageFile) {
                try {
                    // Upload to S3
                    $filename = time() . '_' . \Illuminate\Support\Str::random(10) . '_' . $imageFile->getClientOriginalName();
                    $directory = 'car_listing_edits/' . $edit->id;
                    $path = Storage::disk('s3')->putFileAs($directory, $imageFile, $filename, 'public');
                    
                    if (!$path) {
                        Log::error('Failed to upload image to S3', [
                            'edit_id' => $edit->id,
                            'order' => $order
                        ]);
                        continue;
                    }
                    
                    $mimeType = $imageFile->getMimeType();
                    
                    Media::create([
                        'modelable_type' => CarListingEdit::class,
                        'modelable_id' => $edit->id,
                        'media' => $filename,
                        'path_file_name' => $path,
                        'file_type' => $mimeType,
                        'info' => $order === 0 ? 'primary' : 'secondary',
                        'order' => $order++,
                    ]);
                    
                } catch (\Exception $e) {
                    Log::warning('Failed to process new image file for car listing edit: ' . $e->getMessage());
                }
            }
        }
        
        // Step 3: Handle new base64 images (JSON)
        if (!empty($newImageBase64)) {
            foreach ($newImageBase64 as $base64Image) {
                try {
                    $extension = 'jpg';
                    $mimeType = 'image/jpeg';
                    $base64Payload = $base64Image;
                    
                    if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $base64Image, $matches)) {
                        $mimeType = strtolower($matches[1]);
                        $base64Payload = $matches[2];
                        
                        $extensionMap = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                        ];
                        $extension = $extensionMap[$mimeType] ?? 'jpg';
                    }
                    
                    $imageData = base64_decode($base64Payload);
                    if ($imageData === false) {
                        continue;
                    }
                    
                    $filename = 'car_listing_edit_' . $edit->id . '_' . ($order + 1) . '_' . time() . '.' . $extension;
                    $path = 'car_listing_edits/' . $edit->id . '/' . $filename;
                    
                    Storage::disk('s3')->put($path, $imageData, 'public');
                    
                    Media::create([
                        'modelable_type' => CarListingEdit::class,
                        'modelable_id' => $edit->id,
                        'media' => $filename,
                        'path_file_name' => $path,
                        'file_type' => $mimeType,
                        'info' => $order === 0 ? 'primary' : 'secondary',
                        'order' => $order++,
                    ]);
                    
                } catch (\Exception $e) {
                    Log::warning('Failed to process new base64 image for car listing edit: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Handle image file uploads from form-data (legacy method for backward compatibility)
     */
    public function handleFormDataImages(CarListingEdit $edit, array $imageFiles): void
    {
        foreach ($imageFiles as $index => $imageFile) {
            try {
                // Upload to S3
                $filename = time() . '_' . \Illuminate\Support\Str::random(10) . '_' . $imageFile->getClientOriginalName();
                $directory = 'car_listing_edits/' . $edit->id;
                $path = Storage::disk('s3')->putFileAs($directory, $imageFile, $filename, 'public');
                
                if (!$path) {
                    Log::error('Failed to upload image to S3', [
                        'edit_id' => $edit->id,
                        'index' => $index
                    ]);
                    continue;
                }
                
                $url = Storage::disk('s3')->url($path);
                $mimeType = $imageFile->getMimeType();
                
                Media::create([
                    'modelable_type' => CarListingEdit::class,
                    'modelable_id' => $edit->id,
                    'media' => $filename,
                    'path_file_name' => $path,
                    'file_type' => $mimeType,
                    'info' => $index === 0 ? 'primary' : 'secondary',
                    'order' => $index,
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Failed to process image file for car listing edit: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle image uploads from base64 (JSON requests)
     */
    private function handleImages(CarListingEdit $edit, array $images): void
    {
        foreach ($images as $index => $base64Image) {
            try {
                $extension = 'jpg';
                $mimeType = 'image/jpeg';
                $base64Payload = $base64Image;
                
                if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $base64Image, $matches)) {
                    $mimeType = strtolower($matches[1]);
                    $base64Payload = $matches[2];
                    
                    $extensionMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    ];
                    $extension = $extensionMap[$mimeType] ?? 'jpg';
                }
                
                $imageData = base64_decode($base64Payload);
                if ($imageData === false) {
                    continue;
                }
                
                $filename = 'car_listing_edit_' . $edit->id . '_' . ($index + 1) . '_' . time() . '.' . $extension;
                $path = 'car_listing_edits/' . $edit->id . '/' . $filename;
                
                Storage::disk('s3')->put($path, $imageData, 'public');
                $url = Storage::disk('s3')->url($path);
                
                Media::create([
                    'modelable_type' => CarListingEdit::class,
                    'modelable_id' => $edit->id,
                    'media' => $filename,
                    'path_file_name' => $path,
                    'file_type' => $mimeType,
                    'info' => $index === 0 ? 'primary' : 'secondary',
                    'order' => $index,
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Failed to process image for car listing edit: ' . $e->getMessage());
            }
        }
    }
    
    // Helper methods (similar to CarListingApiController)
    private function getFuelTypeId($fuelTypeName)
    {
        if (!$fuelTypeName) return null;
        $fuelType = \App\Models\FuelType::where('name', $fuelTypeName)->first();
        return $fuelType ? $fuelType->id : null;
    }
    
    private function getCylinderId($cylinders)
    {
        if (!$cylinders) return null;
        $cylinder = \App\Models\CylinderOption::where('name', $cylinders)->first();
        return $cylinder ? $cylinder->id : null;
    }
    
    private function getTransmissionId($transmissionName)
    {
        if (!$transmissionName) return null;
        $transmission = \App\Models\Transmission::where('name', $transmissionName)->first();
        return $transmission ? $transmission->id : null;
    }
    
    private function getExteriorColorId($colorName)
    {
        if (!$colorName) return null;
        $color = \App\Models\ExteriorColor::where('name', $colorName)->first();
        return $color ? $color->id : null;
    }
    
    private function getDriveTypeId($driveTypeName)
    {
        if (!$driveTypeName) return null;
        $driveType = \App\Models\DriveType::where('name', $driveTypeName)->first();
        return $driveType ? $driveType->id : null;
    }
    
    private function getMileageTypeId($mileageTypeName)
    {
        if (!$mileageTypeName) return 'KM';
        $mileageType = \App\Models\MileageType::where('name', $mileageTypeName)->first();
        return $mileageType ? $mileageType->id : null;
    }
    
    private function normalizeGccImport($value): ?string
    {
        if ($value === null) return null;
        if (is_array($value)) return null;
        if (is_bool($value)) return $value ? 'GCC' : 'Import';
        
        $stringValue = trim((string) $value);
        if ($stringValue === '') return null;
        
        $upperValue = strtoupper($stringValue);
        $mapping = [
            '1' => 'GCC', 'TRUE' => 'GCC', 'YES' => 'GCC', 'GCC' => 'GCC',
            '0' => 'Import', 'FALSE' => 'Import', 'NO' => 'Import', 'IMPORT' => 'Import',
        ];
        
        return $mapping[$upperValue] ?? $stringValue;
    }
    
    private function getFuelTypeName($fuelTypeId): ?string
    {
        if (!$fuelTypeId) return null;
        $fuelType = \App\Models\FuelType::find($fuelTypeId);
        if (!$fuelType) return null;
        
        $fuelTypeMapping = [
            'Petrol' => 'gasoline',
            'Diesel' => 'diesel',
            'Electric' => 'electric',
            'Hybrid' => 'hybrid'
        ];
        
        return $fuelTypeMapping[$fuelType->name] ?? strtolower($fuelType->name);
    }
    
    private function getCylinderCount($cylinderId): ?int
    {
        if (!$cylinderId) return null;
        $cylinder = \App\Models\CylinderOption::find($cylinderId);
        return $cylinder ? (int)$cylinder->name : null;
    }
    
    private function getTransmissionName($transmissionId): ?string
    {
        if (!$transmissionId) return null;
        $transmission = \App\Models\Transmission::find($transmissionId);
        return $transmission ? strtolower($transmission->name) : null;
    }
    
    private function getExteriorColorName($colorId): ?string
    {
        if (!$colorId) return null;
        $color = \App\Models\ExteriorColor::find($colorId);
        return $color ? $color->name : null;
    }
    
    private function getDriveTypeName($driveTypeId): ?string
    {
        if (!$driveTypeId) return null;
        $driveType = \App\Models\DriveType::find($driveTypeId);
        return $driveType ? strtolower($driveType->name) : null;
    }
    
    private function getMileageTypeName($mileageTypeId): ?string
    {
        if (!$mileageTypeId) return 'KM';
        $mileageType = \App\Models\MileageType::find($mileageTypeId);
        return $mileageType ? strtoupper($mileageType->name) : 'KM';
    }
}

