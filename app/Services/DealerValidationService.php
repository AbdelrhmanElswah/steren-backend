<?php

namespace App\Services;

use App\Models\User;
use App\Models\DealerApplication;
use Illuminate\Support\Facades\Log;

class DealerValidationService
{
    /**
     * Check if user is a basic dealer (approved application)
     */
    public function isBasicDealer(User $user): bool
    {
        return $user->hasApprovedDealerApplication();
    }

    /**
     * Check if user is an active dealer (approved, not expired, payment completed)
     */
    public function isActiveDealer(User $user): bool
    {
        return $user->hasActiveDealerApplication();
    }

    /**
     * Check if user is a verified dealer (strictest validation)
     */
    public function isVerifiedDealer(User $user): bool
    {
        return $user->isVerifiedDealer();
    }

    /**
     * Get dealer validation status with detailed information
     */
    public function getDealerValidationStatus(User $user): array
    {
        $application = $user->activeDealerApplication;
        
        if (!$application) {
            return [
                'is_dealer' => false,
                'is_active_dealer' => false,
                'is_verified_dealer' => false,
                'status' => 'not_dealer',
                'message' => 'User is not a dealer',
                'requirements' => [
                    'approved_application' => false,
                    'documents_verified' => false,
                    'business_info_complete' => false,
                    'not_expired' => false,
                ],
                'missing_requirements' => [
                    'Dealer application must be approved',
                    'All required documents must be verified',
                    'Business information must be complete',
                    'Dealer application has expired'
                ]
            ];
        }

        $requirements = [
            'approved_application' => $application->isApproved(),
            'documents_verified' => $application->hasAllDocumentsVerified(),
            'business_info_complete' => $this->hasCompleteBusinessInfo($application),
            'not_expired' => !$application->isExpired(),
        ];

        $isDealer = $requirements['approved_application'];
        $isActiveDealer = $isDealer && $requirements['not_expired'];
        $isVerifiedDealer = $isActiveDealer && $requirements['documents_verified'] && $requirements['business_info_complete'];

        $status = 'not_dealer';
        $message = 'User is not a dealer';

        if ($isVerifiedDealer) {
            $status = 'verified_dealer';
            $message = 'User is a fully verified dealer';
        } elseif ($isActiveDealer) {
            $status = 'active_dealer';
            $message = 'User is an active dealer (verification pending)';
        } elseif ($isDealer) {
            $status = 'basic_dealer';
            $message = 'User is a basic dealer (payment or verification pending)';
        }

        return [
            'is_dealer' => $isDealer,
            'is_active_dealer' => $isActiveDealer,
            'is_verified_dealer' => $isVerifiedDealer,
            'status' => $status,
            'message' => $message,
            'requirements' => $requirements,
            'missing_requirements' => $this->getMissingRequirements($requirements),
            'application' => [
                'id' => $application->id,
                'status' => $application->status?->name,
                'payment_completed' => $application->payment_completed,
                'expires_at' => $application->expires_at,
                'business_license_number' => $application->business_license_number,
                'dealership_company_name' => $application->dealership_company_name,
            ]
        ];
    }

    /**
     * Check if dealer application has complete business information
     */
    protected function hasCompleteBusinessInfo(DealerApplication $application): bool
    {
        return !empty($application->business_license_number) &&
               !empty($application->dealership_company_name) &&
               !empty($application->phone_number) &&
               !empty($application->email) &&
               !empty($application->city_address);
    }

    /**
     * Get missing requirements for dealer verification
     */
    protected function getMissingRequirements(array $requirements): array
    {
        $missing = [];
        
        if (!isset($requirements['approved_application']) || !$requirements['approved_application']) {
            $missing[] = 'Dealer application must be approved';
        }
        
        if (!isset($requirements['documents_verified']) || !$requirements['documents_verified']) {
            $missing[] = 'All required documents must be verified';
        }
        
        if (!isset($requirements['business_info_complete']) || !$requirements['business_info_complete']) {
            $missing[] = 'Business information must be complete';
        }
        
        if (!isset($requirements['not_expired']) || !$requirements['not_expired']) {
            $missing[] = 'Dealer application has expired';
        }
        
        return $missing;
    }

    /**
     * Validate dealer access for specific operations
     */
    public function validateDealerAccess(User $user, string $operation): array
    {
        $validationStatus = $this->getDealerValidationStatus($user);
        
        // Define access levels for different operations
        $accessRequirements = [
            'view_listings' => 'basic_dealer',
            'create_listings' => 'active_dealer',
            'access_studios' => 'verified_dealer',
            'auto_studio' => 'verified_dealer',
            'top_picks' => 'verified_dealer',
            'analytics' => 'verified_dealer',
            'priority_support' => 'verified_dealer',
        ];

        $requiredLevel = $accessRequirements[$operation] ?? 'verified_dealer';
        
        $hasAccess = match($requiredLevel) {
            'basic_dealer' => $validationStatus['is_dealer'],
            'active_dealer' => $validationStatus['is_active_dealer'],
            'verified_dealer' => $validationStatus['is_verified_dealer'],
            default => false
        };

        return [
            'has_access' => $hasAccess,
            'required_level' => $requiredLevel,
            'current_level' => $validationStatus['status'],
            'validation_status' => $validationStatus,
            'message' => $hasAccess ? 
                "Access granted for {$operation}" : 
                "Access denied for {$operation}. Required: {$requiredLevel}, Current: {$validationStatus['status']}"
        ];
    }

    /**
     * Log dealer access attempts for security monitoring
     */
    public function logDealerAccessAttempt(User $user, string $operation, bool $granted): void
    {
        Log::info('Dealer access attempt', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'operation' => $operation,
            'granted' => $granted,
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
