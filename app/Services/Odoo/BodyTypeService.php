<?php

namespace App\Services\Odoo;

use App\Models\BodyType;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BodyTypeService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new BodyType());
    }

    public function create(array $data): BodyType
    {
        $startTime = microtime(true);
        
        try {
            // Create the body type
            $bodyType = parent::create($data);
            
            // Handle image if provided
            if (!empty($data['image'])) {
                $this->handleImage($bodyType, $data['image']);
            }
            
            $this->errorTracker->trackPerformance(
                'create_BodyType',
                microtime(true) - $startTime,
                ['model' => 'BodyType', 'data_keys' => array_keys($data)]
            );
            
            return $bodyType;
        } catch (\Exception $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'create',
                'model' => 'BodyType',
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    public function updateByOdooId(string $odooId, array $data): BodyType
    {
        try {
            // Update the body type
            $bodyType = parent::updateByOdooId($odooId, $data);
            
            // Handle image if provided
            if (isset($data['image'])) {
                // Delete existing media if any
                $bodyType->media()?->delete();
                
                if (!empty($data['image'])) {
                    $this->handleImage($bodyType, $data['image']);
                }
            }
            
            return $bodyType;
        } catch (\Exception $e) {
            $this->errorTracker->trackError($e, request(), [
                'operation' => 'updateByOdooId',
                'model' => 'BodyType',
                'odoo_id' => $odooId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    private function handleImage(BodyType $bodyType, string $imageData): void
    {
        try {
            // Decode base64 image data
            $imageData = str_replace('data:image/png;base64,', '', $imageData);
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData);
            
            if ($decodedImage === false) {
                throw new \Exception('Invalid base64 image data');
            }
            
            // Generate unique filename
            $filename = 'bodytype_' . $bodyType->id . '_' . Str::random(10) . '.png';
            $path = 'bodytypes/' . $filename;
            
            // Store the image
            Storage::disk('public')->put($path, $decodedImage);
            
            // Create media record
            Media::create([
                'modelable_type' => BodyType::class,
                'modelable_id' => $bodyType->id,
                'media' => $imageData,
                'path_file_name' => $path,
                'file_type' => 'image/png',
                'info' => 'icon',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to handle body type image', [
                'body_type_id' => $bodyType->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
