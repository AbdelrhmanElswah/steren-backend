<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Get all report reasons for the frontend
     */
    public function getReportReasons(): JsonResponse
    {
        try {
            $reasons = [
                [
                    'value' => 'inaccurate_information',
                    'label' => 'Inaccurate Information',
                    'description' => 'The listing contains false or misleading information'
                ],
                [
                    'value' => 'offensive_content',
                    'label' => 'Offensive Content',
                    'description' => 'The content is inappropriate or offensive'
                ],
                [
                    'value' => 'spam',
                    'label' => 'Spam',
                    'description' => 'This appears to be spam or unwanted content'
                ],
                [
                    'value' => 'inappropriate_language',
                    'label' => 'Inappropriate Language',
                    'description' => 'The listing contains inappropriate language'
                ],
                [
                    'value' => 'copyright_infringement',
                    'label' => 'Copyright Infringement',
                    'description' => 'This content violates copyright laws'
                ],
                [
                    'value' => 'other',
                    'label' => 'Other',
                    'description' => 'Other reason not listed above'
                ]
            ];

            return $this->successResponse($reasons, 'Report reasons retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve report reasons: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's reports
     */
    public function getUserReports(Request $request): AnonymousResourceCollection
    {
        try {
            $reports = Report::where('user_id', auth()->id())
                ->with('reportable')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return ReportResource::collection($reports);
        } catch (\Exception $e) {
            return ReportResource::collection([])->additional([
                'success' => false,
                'message' => 'Failed to retrieve user reports: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get report details
     */
    public function getReportDetails($reportId): JsonResponse
    {
        try {
            $report = Report::with(['reportable', 'reviewer'])->findOrFail($reportId);

            // Check if user can view this report
            if ($report->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return $this->unauthorizedResponse();
            }

            $data = [
                'id' => $report->id,
                'reason' => $report->reason,
                'reason_label' => $report->reason_label,
                'message' => $report->message,
                'status' => $report->status,
                'status_label' => $report->status_label,
                'is_confirmed' => $report->is_confirmed,
                'evidence' => $report->evidence,
                'admin_notes' => $report->admin_notes,
                'created_at' => $report->created_at,
                'reviewed_at' => $report->reviewed_at,
                'reviewer' => $report->reviewer ? [
                    'id' => $report->reviewer->id,
                    'name' => $report->reviewer->full_name,
                ] : null,
                'reportable' => [
                    'type' => $report->reportable_type,
                    'id' => $report->reportable_id,
                    'data' => $report->reportable,
                ]
            ];

            return $this->successResponse($data, 'Report details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve report details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get report statistics for admin
     */
    public function getReportStatistics(): JsonResponse
    {
        try {
            // Only allow admin access
            if (!auth()->user()->hasRole('admin')) {
                return $this->unauthorizedResponse();
            }

            $statistics = [
                'total_reports' => Report::count(),
                'pending_reports' => Report::pending()->count(),
                'under_review_reports' => Report::underReview()->count(),
                'resolved_reports' => Report::resolved()->count(),
                'dismissed_reports' => Report::dismissed()->count(),
                'confirmed_reports' => Report::confirmed()->count(),
                'reports_by_reason' => [
                    'inaccurate_information' => Report::byReason('inaccurate_information')->count(),
                    'offensive_content' => Report::byReason('offensive_content')->count(),
                    'spam' => Report::byReason('spam')->count(),
                    'inappropriate_language' => Report::byReason('inappropriate_language')->count(),
                    'copyright_infringement' => Report::byReason('copyright_infringement')->count(),
                    'other' => Report::byReason('other')->count(),
                ]
            ];

            return $this->successResponse($statistics, 'Report statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve report statistics: ' . $e->getMessage(), 500);
        }
    }
}
