<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarListing;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CarListingDetailsController extends Controller
{
    use ApiResponse;

    /**
     * Get all data related to car listing by its ID
     */
    public function getCarListingDetails($id): JsonResponse
    {
        try {
            $listing = CarListing::with([
                'brand:id,name,odoo_id',
                'carModel:id,name,car_brand_id',
                'carTrim:id,name,car_model_id',
                'bodyType:id,name',
                'location:id,name',
                'studio:id,name',
                
                'carCondition:id,name',
                'fuelType:id,name',
                'transmission:id,name',
                'exteriorColor:id,name',
                'driveType:id,name',
                'cylinder:id,name',
                'mileageType:id,name',
                'importType:id,name',
                'warrantyStatus:id,name',
                
                'user:id,full_name,display_name,phone,email',
                
                // Media (optimized)
                'media' => function($query) {
                    $query->select('id', 'modelable_id', 'path_file_name', 'file_type', 'order', 'info')
                          ->orderBy('order')
                          ->orderBy('created_at');
                },
                
                // Features and specifications (conditional loading)
                'features' => function($query) {
                    $query->select('car_features.id', 'car_features.name', 'car_features.category', 'car_features.description', 'car_features.icon')
                          ->orderBy('category')
                          ->orderBy('name');
                },
                'specifications' => function($query) {
                    $query->select('id', 'car_listing_id', 'name', 'value', 'specification_type')
                          ->orderBy('specification_type')
                          ->orderBy('name');
                },
                
                // Promotions
                'listingPromotion:id,name,type,price,currency,duration_days,is_active',
                
                // Documents (conditional)
                'documents' => function($query) {
                    $query->select('id', 'documentable_id', 'documentable_type', 'file_path', 'url', 'file_name', 'category', 'is_verified')
                          ->where('is_verified', true);
                },
                
                // Inspection (conditional)
                'inspection:id,car_listing_id,inspection_number,inspection_date,status,notes,is_verified,verified_at,right_side,left_side,roof,front,back',
                
                // Carfax (conditional)
                'carfaxReport:id,car_listing_id,vin,report_data,generated_at',
                
                // Car damages (conditional)
                'carDamages:id,car_listing_id,car_part_id,condition,description',
                
                // Analytics (minimal)
                'analytics' => function($query) {
                    $query->select('id', 'car_listing_id', 'event_type', 'count', 'date')
                          ->orderBy('date', 'desc')
                          ->limit(10);
                }
            ])->findOrFail($id);

            // Group features by category
            $featuresByCategory = $listing->features->groupBy('category')->map(function ($features) {
                return $features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'description' => $feature->description,
                        'icon' => $feature->icon,
                        'category' => $feature->category,
                        'is_included' => $feature->pivot->is_included ?? false,
                        'notes' => $feature->pivot->notes,
                    ];
                });
            });

            // Group specifications by type
            $specificationsByType = $listing->specifications->groupBy('specification_type')->map(function ($specs) {
                return $specs->map(function ($spec) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'value' => $spec->value,
                        'unit' => $spec->unit,
                        'formatted_value' => $spec->formatted_value,
                        'description' => $spec->description,
                        'is_highlighted' => $spec->is_highlighted,
                    ];
                });
            });

            // Group documents by category
            $documentsByCategory = $listing->documents->groupBy('category')->map(function ($docs) {
                return $docs->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'file_path' => $doc->file_path,
                        'file_type' => $doc->file_type,
                        'file_size' => $doc->file_size,
                        'is_verified' => $doc->is_verified,
                        'uploaded_at' => $doc->created_at,
                    ];
                });
            });

            // Get analytics summary
            $analyticsSummary = [
                'total_views' => $listing->total_views,
                'total_shares' => $listing->total_shares,
                'total_call_taps' => $listing->total_call_taps,
                'total_whatsapp_clicks' => $listing->total_whatsapp_clicks,
                'total_sms_clicks' => $listing->total_sms_clicks,
                'total_email_clicks' => $listing->total_email_clicks,
                'total_watchlist_saves' => $listing->total_watchlist_saves,
                'days_active' => $listing->days_active,
            ];

            // Get damage status and details
            $damageDetails = [
                'status' => $listing->damage_status,
                'damages' => $listing->carDamages->map(function ($damage) {
                    return [
                        'id' => $damage->id,
                        'part_name' => $damage->part_name,
                        'condition' => $damage->condition,
                        'description' => $damage->description,
                        'severity' => $damage->severity,
                        'repair_cost' => $damage->repair_cost,
                        'is_repaired' => $damage->is_repaired,
                    ];
                })
            ];

            // Get inspection details
            $inspectionDetails = null;
            if ($listing->inspection) {
                $inspectionDetails = [
                    'id' => $listing->inspection->id,
                    'inspection_number' => $listing->inspection->inspection_number,
                    'inspection_date' => $listing->inspection->inspection_date,
                    'status' => $listing->inspection->status,
                    'notes' => $listing->inspection->notes,
                    'is_verified' => $listing->inspection->is_verified,
                    'verified_at' => $listing->inspection->verified_at,
                    'inspection_sides' => [
                        'right_side' => $listing->inspection->right_side,
                        'left_side' => $listing->inspection->left_side,
                        'roof' => $listing->inspection->roof,
                        'front' => $listing->inspection->front,
                        'back' => $listing->inspection->back,
                    ],
                ];
            }

            // Get Carfax report details
            $carfaxDetails = null;
            if ($listing->carfaxReport) {
                $carfaxDetails = [
                    'id' => $listing->carfaxReport->id,
                    'vin' => $listing->carfaxReport->vin,
                    'accident_history' => $listing->carfaxReport->accident_history,
                    'service_history' => $listing->carfaxReport->service_history,
                    'ownership_history' => $listing->carfaxReport->ownership_history,
                    'title_issues' => $listing->carfaxReport->title_issues,
                    'flood_damage' => $listing->carfaxReport->flood_damage,
                    'lemon_title' => $listing->carfaxReport->lemon_title,
                    'reported_at' => $listing->carfaxReport->created_at,
                ];
            }

            // Get promotion details
            $promotionDetails = null;
            if ($listing->listingPromotion) {
                $promotionDetails = [
                    'id' => $listing->listingPromotion->id,
                    'name' => $listing->listingPromotion->name,
                    'type' => $listing->listingPromotion->type,
                    'price' => $listing->listingPromotion->price,
                    'currency' => $listing->listingPromotion->currency,
                    'duration_days' => $listing->listingPromotion->duration_days,
                    'features' => $listing->listingPromotion->features,
                    'is_active' => $listing->listingPromotion->is_active,
                ];
            }

            // Get active boosts
            $activeBoosts = $listing->activeBoosts->map(function ($boost) {
                return [
                    'id' => $boost->id,
                    'type' => $boost->type,
                    'price_paid' => $boost->price_paid,
                    'currency' => $boost->currency,
                    'started_at' => $boost->started_at,
                    'expires_at' => $boost->expires_at,
                    'is_active' => $boost->expires_at > now(),
                ];
            });

            $data = [
                'listing' => [
                    'id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'title' => $listing->title,
                    'full_title' => $listing->full_title,
                    'vin' => $listing->vin,
                    'price' => $listing->price,
                    'formatted_price' => $listing->formatted_price,
                    'price_currency' => $listing->price_currency,
                    'year' => $listing->year,
                    'trim' => $listing->trim,
                    'doors' => $listing->doors,
                    'mileage' => $listing->mileage,
                    'formatted_mileage' => $listing->formatted_mileage,
                    'origin_country' => $listing->origin_country,
                    'gcc_import' => $listing->gcc_import,
                    'plate_status' => $listing->plate_status,
                    'condition_type' => $listing->condition_type,
                    'has_warranty' => $listing->has_warranty,
                    'is_inspected' => $listing->is_inspected,
                    'is_verified' => $listing->is_verified,
                    'has_insurance' => $listing->has_insurance,
                    'status' => $listing->status,
                    'status_label' => $listing->status_label,
                    'operator_decision' => $listing->operator_decision,
                    'approved_by' => $listing->approved_by,
                    'approved_at' => $listing->approved_at,
                    'rejection_reason' => $listing->rejection_reason,
                    'rejected_by' => $listing->rejected_by,
                    'rejected_at' => $listing->rejected_at,
                    'is_featured' => $listing->is_featured,
                    'is_new_arrival' => $listing->is_new_arrival,
                    'description' => $listing->description,
                    'sold_at' => $listing->sold_at,
                    'featured_at' => $listing->featured_at,
                    'paused_at' => $listing->paused_at,
                    'archived_at' => $listing->archived_at,
                    'created_at' => $listing->created_at,
                    'updated_at' => $listing->updated_at,
                ],
                
                // Engine and performance specs
                'specifications' => [
                    'engine_size' => $listing->engine_size,
                    'engine_type' => $listing->engine_type,
                    'horsepower' => $listing->horsepower,
                    'torque' => $listing->torque,
                    'acceleration_0_100' => $listing->acceleration_0_100,
                    'top_speed' => $listing->top_speed,
                    'seating_capacity' => $listing->seating_capacity,
                    'mileage_efficiency' => $listing->mileage_efficiency,
                    'formatted_mileage_efficiency' => $listing->formatted_mileage_efficiency,
                    'length' => $listing->length,
                    'width' => $listing->width,
                    'height' => $listing->height,
                    'formatted_dimensions' => $listing->formatted_dimensions,
                ],
                
                // Relationships
                'brand' => $listing->brand,
                'car_model' => $listing->carModel,
                'car_trim' => $listing->carTrim,
                'body_type' => $listing->bodyType,
                'location' => $listing->location,
                'studio' => $listing->studio,
                'car_condition' => $listing->carCondition,
                'fuel_type' => $listing->fuelType,
                'transmission' => $listing->transmission,
                'exterior_color' => $listing->exteriorColor,
                'drive_type' => $listing->driveType,
                'cylinder' => $listing->cylinder,
                'mileage_type' => $listing->mileageType,
                'import_type' => $listing->importType,
                'warranty_status' => $listing->warrantyStatus,
                'user' => $listing->user,
                
                // Media
                'media' => $listing->media,
                
                // Features and specifications
                'features_by_category' => $featuresByCategory,
                'detailed_specifications' => $specificationsByType,
                
                // Badges and status
                'badges' => $listing->badges,
                'active_badges' => $listing->active_badges,
                'status_badge' => $listing->status_badge,
                
                // Documents
                'documents_by_category' => $documentsByCategory,
                
                // Inspection and reports
                'inspection' => $inspectionDetails,
                'carfax_report' => $carfaxDetails,
                'damage_details' => $damageDetails,
                
                // Analytics
                'analytics' => $analyticsSummary,
                
                // Promotions and boosts
                'promotion' => $promotionDetails,
                'active_boosts' => $activeBoosts,
                'promotion_type' => $listing->promotion_type,
                
                // Reports
                'reports' => $listing->reports,
            ];

            return $this->successResponse($data, 'Car listing details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listing details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all car listing data with media
     */
    public function getCarListingWithMedia($id): JsonResponse
    {
        try {
            $listing = CarListing::with([
                // Basic relationships
                'brand', 'carModel', 'carTrim', 'bodyType', 'location', 'studio',
                'carCondition', 'fuelType', 'transmission', 'exteriorColor', 
                'driveType', 'cylinder', 'mileageType', 'importType', 'warrantyStatus',
                'status',
                
                // User and contact info
                'user',
                
                // Media with detailed information
                'media' => function($query) {
                    $query->orderBy('order')->orderBy('created_at');
                },
                
                // Features and specifications
                'features' => function($query) {
                    $query->orderBy('category')->orderBy('name');
                },
                'specifications' => function($query) {
                    $query->orderBy('specification_type')->orderBy('name');
                },
                
                // Badges and promotions
                'badges',
                'listingPromotion',
                
                // Documents and reports
                'documents',
                'inspection',
                'carfaxReport',
                'carDamages',
                
                // Analytics
                'analytics',
                
                // Favorites
                'favorites',
                
                // Reports
                'reports',
                
                // Boosts
                'boosts',
                'activeBoosts'
            ])->findOrFail($id);

            // Group media by type
            $mediaByType = $listing->media->groupBy('media_type')->map(function ($media) {
                return $media->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'file_name' => $item->file_name,
                        'file_path' => $item->file_path,
                        'file_url' => $item->file_url,
                        'file_type' => $item->file_type,
                        'media_type' => $item->media_type,
                        'file_size' => $item->file_size,
                        'mime_type' => $item->mime_type,
                        'is_primary' => $item->is_primary,
                        'order' => $item->order,
                        'alt_text' => $item->alt_text,
                        'caption' => $item->caption,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });
            });

            // Get primary media
            $primaryMedia = $listing->media->where('is_primary', true)->first();
            
            // Get all images
            $images = $listing->media->filter(function ($media) {
                return str_starts_with($media->file_type, 'image/');
            });
            
            // Get all videos
            $videos = $listing->media->filter(function ($media) {
                return str_starts_with($media->file_type, 'video/');
            });
            
            // Get all documents
            $documents = $listing->media->filter(function ($media) {
                return str_starts_with($media->file_type, 'application/');
            });

            // Group features by category
            $featuresByCategory = $listing->features->groupBy('category')->map(function ($features) {
                return $features->map(function ($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'description' => $feature->description,
                        'icon' => $feature->icon,
                        'category' => $feature->category,
                        'is_included' => $feature->pivot->is_included ?? false,
                        'notes' => $feature->pivot->notes,
                    ];
                });
            });

            // Group specifications by type
            $specificationsByType = $listing->specifications->groupBy('specification_type')->map(function ($specs) {
                return $specs->map(function ($spec) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'value' => $spec->value,
                        'unit' => $spec->unit,
                        'formatted_value' => $spec->formatted_value,
                        'description' => $spec->description,
                        'is_highlighted' => $spec->is_highlighted,
                    ];
                });
            });

            // Get analytics summary
            $analyticsSummary = [
                'total_views' => $listing->total_views,
                'total_shares' => $listing->total_shares,
                'total_call_taps' => $listing->total_call_taps,
                'total_whatsapp_clicks' => $listing->total_whatsapp_clicks,
                'total_sms_clicks' => $listing->total_sms_clicks,
                'total_email_clicks' => $listing->total_email_clicks,
                'total_watchlist_saves' => $listing->total_watchlist_saves,
                'days_active' => $listing->days_active,
            ];

            $data = [
                'listing' => [
                    'id' => $listing->id,
                    'odoo_id' => $listing->odoo_id,
                    'title' => $listing->title,
                    'full_title' => $listing->full_title,
                    'vin' => $listing->vin,
                    'price' => $listing->price,
                    'formatted_price' => $listing->formatted_price,
                    'price_currency' => $listing->price_currency,
                    'year' => $listing->year,
                    'trim' => $listing->trim,
                    'doors' => $listing->doors,
                    'mileage' => $listing->mileage,
                    'formatted_mileage' => $listing->formatted_mileage,
                    'origin_country' => $listing->origin_country,
                    'gcc_import' => $listing->gcc_import,
                    'plate_status' => $listing->plate_status,
                    'condition_type' => $listing->condition_type,
                    'has_warranty' => $listing->has_warranty,
                    'is_inspected' => $listing->is_inspected,
                    'is_verified' => $listing->is_verified,
                    'has_insurance' => $listing->has_insurance,
                    'status' => $listing->status,
                    'status_label' => $listing->status_label,
                    'operator_decision' => $listing->operator_decision,
                    'approved_by' => $listing->approved_by,
                    'approved_at' => $listing->approved_at,
                    'rejection_reason' => $listing->rejection_reason,
                    'rejected_by' => $listing->rejected_by,
                    'rejected_at' => $listing->rejected_at,
                    'is_featured' => $listing->is_featured,
                    'is_new_arrival' => $listing->is_new_arrival,
                    'description' => $listing->description,
                    'sold_at' => $listing->sold_at,
                    'featured_at' => $listing->featured_at,
                    'paused_at' => $listing->paused_at,
                    'archived_at' => $listing->archived_at,
                    'created_at' => $listing->created_at,
                    'updated_at' => $listing->updated_at,
                ],
                
                // Engine and performance specs
                'specifications' => [
                    'engine_size' => $listing->engine_size,
                    'engine_type' => $listing->engine_type,
                    'horsepower' => $listing->horsepower,
                    'torque' => $listing->torque,
                    'acceleration_0_100' => $listing->acceleration_0_100,
                    'top_speed' => $listing->top_speed,
                    'seating_capacity' => $listing->seating_capacity,
                    'mileage_efficiency' => $listing->mileage_efficiency,
                    'formatted_mileage_efficiency' => $listing->formatted_mileage_efficiency,
                    'length' => $listing->length,
                    'width' => $listing->width,
                    'height' => $listing->height,
                    'formatted_dimensions' => $listing->formatted_dimensions,
                ],
                
                // Relationships
                'brand' => $listing->brand,
                'car_model' => $listing->carModel,
                'car_trim' => $listing->carTrim,
                'body_type' => $listing->bodyType,
                'location' => $listing->location,
                'studio' => $listing->studio,
                'car_condition' => $listing->carCondition,
                'fuel_type' => $listing->fuelType,
                'transmission' => $listing->transmission,
                'exterior_color' => $listing->exteriorColor,
                'drive_type' => $listing->driveType,
                'cylinder' => $listing->cylinder,
                'mileage_type' => $listing->mileageType,
                'import_type' => $listing->importType,
                'warranty_status' => $listing->warrantyStatus,
                'user' => $listing->user,
                'status' => $listing->status,
                
                // Media information
                'media' => [
                    'all_media' => $listing->media,
                    'media_by_type' => $mediaByType,
                    'primary_media' => $primaryMedia,
                    'images' => $images,
                    'videos' => $videos,
                    'documents' => $documents,
                    'total_media_count' => $listing->media->count(),
                    'image_count' => $images->count(),
                    'video_count' => $videos->count(),
                    'document_count' => $documents->count(),
                ],
                
                // Features and specifications
                'features_by_category' => $featuresByCategory,
                'detailed_specifications' => $specificationsByType,
                
                // Badges and status
                'badges' => $listing->badges,
                'active_badges' => $listing->active_badges,
                'status_badge' => $listing->status_badge,
                
                // Documents
                'documents' => $listing->documents,
                
                // Inspection and reports
                'inspection' => $listing->inspection,
                'carfax_report' => $listing->carfaxReport,
                'damage_details' => [
                    'status' => $listing->damage_status,
                    'damages' => $listing->carDamages,
                ],
                
                // Analytics
                'analytics' => $analyticsSummary,
                
                // Promotions and boosts
                'promotion' => $listing->listingPromotion,
                'active_boosts' => $listing->activeBoosts,
                'promotion_type' => $listing->promotion_type,
                
                // Reports
                'reports' => $listing->reports,
            ];

            return $this->successResponse($data, 'Car listing with media retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve car listing with media: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's own car listings
     */
    public function getUserListings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $query = CarListing::with([
                'brand', 'carModel', 'carTrim', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder', 'status',
                'listingPromotion', 'analytics'
            ])
            ->where('user_id', $user->id);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('is_featured')) {
                $query->where('is_featured', $request->is_featured === 'true');
            }

            if ($request->filled('is_promoted')) {
                $query->where('is_promoted', $request->is_promoted === 'true');
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhereHas('brand', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('carModel', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'latest');
            switch ($sortBy) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'year_high':
                    $query->orderBy('year', 'desc');
                    break;
                case 'year_low':
                    $query->orderBy('year', 'asc');
                    break;
                case 'mileage_high':
                    $query->orderBy('mileage', 'desc');
                    break;
                case 'mileage_low':
                    $query->orderBy('mileage', 'asc');
                    break;
                case 'status':
                    $query->orderBy('status', 'asc')->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $perPage = $request->get('per_page', 7);
            $listings = $query->paginate($perPage);

            // Get summary statistics
            $stats = [
                'total_listings' => CarListing::where('user_id', $user->id)->count(),
                'active_listings' => CarListing::where('user_id', $user->id)->where('status', 'active')->count(),
                'sold_listings' => CarListing::where('user_id', $user->id)->where('status', 'sold')->count(),
                'paused_listings' => CarListing::where('user_id', $user->id)->where('status', 'paused')->count(),
                'pending_listings' => CarListing::where('user_id', $user->id)->where('status', 'pending')->count(),
                'rejected_listings' => CarListing::where('user_id', $user->id)->where('status', 'rejected')->count(),
                'featured_listings' => CarListing::where('user_id', $user->id)->where('is_featured', true)->count(),
                'promoted_listings' => CarListing::where('user_id', $user->id)->where('is_promoted', true)->count(),
            ];

            // Get total analytics for user's listings
            $totalViews = CarListing::where('user_id', $user->id)
                ->join('car_listing_analytics', 'car_listings.id', '=', 'car_listing_analytics.car_listing_id')
                ->where('car_listing_analytics.event_type', 'view')
                ->count();

            $totalShares = CarListing::where('user_id', $user->id)
                ->join('car_listing_analytics', 'car_listings.id', '=', 'car_listing_analytics.car_listing_id')
                ->where('car_listing_analytics.event_type', 'share')
                ->count();

            $totalCalls = CarListing::where('user_id', $user->id)
                ->join('car_listing_analytics', 'car_listings.id', '=', 'car_listing_analytics.car_listing_id')
                ->where('car_listing_analytics.event_type', 'call_tap')
                ->count();

            $stats['total_views'] = $totalViews;
            $stats['total_shares'] = $totalShares;
            $stats['total_calls'] = $totalCalls;

            $data = [
                'listings' => $listings->items(),
                'pagination' => [
                    'total' => $listings->total(),
                    'per_page' => $listings->perPage(),
                    'current_page' => $listings->currentPage(),
                    'last_page' => $listings->lastPage(),
                    'from' => $listings->firstItem(),
                    'to' => $listings->lastItem(),
                ],
                'statistics' => $stats,
                'filters_applied' => [
                    'status' => $request->get('status'),
                    'is_featured' => $request->get('is_featured'),
                    'is_promoted' => $request->get('is_promoted'),
                    'search' => $request->get('search'),
                    'sort' => $request->get('sort'),
                ],
            ];

            return $this->successResponse($data, 'User listings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user listings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get car listings that match with other brand and model
     */
    public function getSimilarCars(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'brand_id' => 'required|integer|exists:car_brands,id',
                'model_id' => 'required|integer|exists:car_models,id',
                'exclude_id' => 'nullable|integer|exists:car_listings,id',
                'limit' => 'nullable|integer|min:1|max:50',
                'price_range' => 'nullable|array',
                'price_range.min' => 'nullable|numeric|min:0',
                'price_range.max' => 'nullable|numeric|min:0',
                'year_range' => 'nullable|array',
                'year_range.min' => 'nullable|integer|min:1900|max:' . date('Y'),
                'year_range.max' => 'nullable|integer|min:1900|max:' . date('Y'),
            ]);

            $limit = $request->get('limit', 20);
            $excludeId = $request->get('exclude_id');

            $query = CarListing::with([
                'brand', 'carModel', 'bodyType', 'location', 'studio',
                'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                'exteriorColor', 'driveType', 'cylinder'
            ])
            ->where('car_brand_id', $request->brand_id)
            ->where('car_model_id', $request->model_id)
            ->active();

            // Exclude specific listing if provided
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            // Apply price range filter
            if ($request->filled('price_range.min') && $request->filled('price_range.max')) {
                $query->byPriceRange($request->price_range['min'], $request->price_range['max']);
            }

            // Apply year range filter
            if ($request->filled('year_range.min') && $request->filled('year_range.max')) {
                $query->byYearRange($request->year_range['min'], $request->year_range['max']);
            }

            // Order by relevance (newer listings first, then by price)
            $query->orderBy('created_at', 'desc')
                  ->orderBy('price', 'asc');

            $similarCars = $query->limit($limit)->get();

            // Get additional similar cars by brand only (if not enough results)
            if ($similarCars->count() < $limit) {
                $additionalLimit = $limit - $similarCars->count();
                $additionalCars = CarListing::with([
                    'brand', 'carModel', 'bodyType', 'location', 'studio',
                    'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                    'exteriorColor', 'driveType', 'cylinder'
                ])
                ->where('car_brand_id', $request->brand_id)
                ->where('car_model_id', '!=', $request->model_id)
                ->active();

                if ($excludeId) {
                    $additionalCars->where('id', '!=', $excludeId);
                }

                // Exclude already found cars
                $excludeIds = $similarCars->pluck('id')->toArray();
                if (!empty($excludeIds)) {
                    $additionalCars->whereNotIn('id', $excludeIds);
                }

                $additionalCars = $additionalCars->orderBy('created_at', 'desc')
                    ->orderBy('price', 'asc')
                    ->limit($additionalLimit)
                    ->get();

                $similarCars = $similarCars->merge($additionalCars);
            }

            // Get additional similar cars by body type (if still not enough results)
            if ($similarCars->count() < $limit) {
                $additionalLimit = $limit - $similarCars->count();
                
                // Get body type from the original model
                $originalModel = \App\Models\CarModel::find($request->model_id);
                if ($originalModel && $originalModel->body_type_id) {
                    $additionalCars = CarListing::with([
                        'brand', 'carModel', 'bodyType', 'location', 'studio',
                        'media', 'badges', 'carCondition', 'fuelType', 'transmission',
                        'exteriorColor', 'driveType', 'cylinder'
                    ])
                    ->where('car_body_type_id', $originalModel->body_type_id)
                    ->where('car_brand_id', '!=', $request->brand_id)
                    ->active();

                    if ($excludeId) {
                        $additionalCars->where('id', '!=', $excludeId);
                    }

                    // Exclude already found cars
                    $excludeIds = $similarCars->pluck('id')->toArray();
                    if (!empty($excludeIds)) {
                        $additionalCars->whereNotIn('id', $excludeIds);
                    }

                    $additionalCars = $additionalCars->orderBy('created_at', 'desc')
                        ->orderBy('price', 'asc')
                        ->limit($additionalLimit)
                        ->get();

                    $similarCars = $similarCars->merge($additionalCars);
                }
            }

            $data = [
                'similar_cars' => $similarCars,
                'total_found' => $similarCars->count(),
                'search_criteria' => [
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'exclude_id' => $excludeId,
                    'price_range' => $request->get('price_range'),
                    'year_range' => $request->get('year_range'),
                ],
                'brand' => \App\Models\CarBrand::find($request->brand_id),
                'model' => \App\Models\CarModel::find($request->model_id),
            ];

            return $this->successResponse($data, 'Similar cars retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . $e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve similar cars: ' . $e->getMessage(), 500);
        }
    }
}
