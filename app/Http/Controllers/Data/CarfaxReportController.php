<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarfaxReportResource;
use App\Http\Requests\Carfax\SubmitCarfaxReportRequest;
use App\Models\CarfaxReport;
use App\Models\CarListing;
use App\Models\Report;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CarfaxReportController extends Controller
{
    use ApiResponse;

    /**
     * Get Carfax report for a specific car listing
     */
    public function getCarfaxReport($carListingId): JsonResponse
    {
        try {
            $carListing = CarListing::with(['carfaxReport'])->findOrFail($carListingId);
            
            if (!$carListing->carfaxReport) {
                return $this->notFoundResponse('No Carfax report found for this car');
            }

            $carfax = new CarfaxReportResource($carListing->carfaxReport);
            return $this->resourceResponse($carfax, 'Carfax report retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Carfax report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Carfax report by VIN
     */
    public function getCarfaxByVin($vin): JsonResponse
    {
        try {
            $carfax = CarfaxReport::where('vin', strtoupper($vin))->first();

            if (!$carfax) {
                return $this->notFoundResponse('No Carfax report found for this VIN');
            }

            $carfaxResource = new CarfaxReportResource($carfax);
            return $this->resourceResponse($carfaxResource, 'Carfax report retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Carfax report by VIN: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Carfax statistics
     */
    public function getCarfaxStatistics(): JsonResponse
    {
        try {
            $statistics = [
                'total_reports' => CarfaxReport::count(),
                'generated_reports' => CarfaxReport::generated()->count(),
                'pending_reports' => CarfaxReport::where('status', 'pending')->count(),
                'failed_reports' => CarfaxReport::where('status', 'failed')->count(),
                'clean_title_reports' => CarfaxReport::cleanTitle()->count(),
                'reports_with_service_history' => CarfaxReport::withServiceHistory()->count(),
                'average_accident_count' => CarfaxReport::whereNotNull('accident_count')->avg('accident_count'),
                'average_owner_count' => CarfaxReport::whereNotNull('owner_count')->avg('owner_count'),
                'reports_by_accident_count' => [
                    'no_accidents' => CarfaxReport::byAccidentCount(0)->count(),
                    'one_accident' => CarfaxReport::where('accident_count', 1)->count(),
                    'multiple_accidents' => CarfaxReport::where('accident_count', '>', 1)->count(),
                ]
            ];

            return $this->successResponse($statistics, 'Carfax statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Carfax statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search Carfax reports
     */
    public function searchCarfaxReports(Request $request): AnonymousResourceCollection
    {
        try {
            $request->validate([
                'vin' => 'nullable|string',
                'has_clean_title' => 'nullable|boolean',
                'has_service_history' => 'nullable|boolean',
                'max_accidents' => 'nullable|integer|min:0',
                'status' => 'nullable|in:pending,generated,failed',
            ]);

            $query = CarfaxReport::with(['carListing.brand', 'carListing.carModel']);

            if ($request->vin) {
                $query->byVin($request->vin);
            }

            if ($request->has_clean_title !== null) {
                $query->where('has_clean_title', $request->has_clean_title);
            }

            if ($request->has_service_history !== null) {
                $query->where('has_service_history', $request->has_service_history);
            }

            if ($request->max_accidents !== null) {
                $query->byAccidentCount($request->max_accidents);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $reports = $query->orderBy('created_at', 'desc')->paginate(10);

            return CarfaxReportResource::collection($reports);
        } catch (\Exception $e) {
            return CarfaxReportResource::collection([])->additional([
                'success' => false,
                'message' => 'Failed to search Carfax reports: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Submit a Carfax report to Odoo
     */
    public function submitCarfaxReport(SubmitCarfaxReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            // Get the car listing and its Odoo ID
            $carListing = CarListing::find($data['car_listing_id']);
            
            if (!$carListing) {
                return $this->errorResponse('Car listing not found', 404);
            }

            if (!$carListing->odoo_id) {
                return $this->errorResponse('Car listing does not have an Odoo ID. Please ensure the listing is synced with Odoo.', 422, [
                    'car_listing_id' => $carListing->id,
                    'reason' => 'missing_odoo_id'
                ]);
            }

            $report = Report::create([
                'user_id' => $request->user()?->id,
                'reportable_type' => CarListing::class,
                'reportable_id' => $data['car_listing_id'],
                'reason' => $data['reason'],
                'message' => $data['message'] ?? null,
                'status' => 'pending',
            ]);

            // Use Odoo ID instead of Laravel ID
            $odooPayload = [
                'car_listing_id' => (int) $carListing->odoo_id, // Send Odoo ID, not Laravel ID
                'reason' => $data['reason'],
                'message' => $data['message'] ?? null,
            ];

            Log::info('CarfaxReportController: Submitting report to Odoo', [
                'laravel_car_listing_id' => $carListing->id,
                'odoo_car_listing_id' => $carListing->odoo_id,
                'payload' => $odooPayload
            ]);

            $response = Http::timeout(30)->post(
                rtrim(config('services.odoo.base_url'), '/') . '/api/listing/report',
                $odooPayload
            );

            if (!$response->successful()) {
                Log::error('CarfaxReportController: Failed to submit report to Odoo', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $odooPayload,
                ]);

                return $this->errorResponse('Failed to submit report to Odoo', 502, [
                    'odoo_status' => $response->status(),
                    'odoo_response' => $response->json(),
                ]);
            }

            $report->update([
                'status' => 'under_review',
                'admin_notes' => 'Report forwarded to Odoo',
            ]);

            return $this->successResponse([
                'report_id' => $report->id,
                'odoo_response' => $response->json(),
            ], 'Carfax report submitted successfully', 201);
        } catch (\Exception $e) {
            Log::error('CarfaxReportController: Error submitting Carfax report', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $data,
            ]);

            return $this->errorResponse('Failed to submit Carfax report: ' . $e->getMessage(), 500);
        }
    }
}
