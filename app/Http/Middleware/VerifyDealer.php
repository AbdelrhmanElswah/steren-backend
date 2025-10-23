<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DealerValidationService;
use Illuminate\Support\Facades\Auth;

class VerifyDealer
{
    protected DealerValidationService $dealerValidationService;

    public function __construct(DealerValidationService $dealerValidationService)
    {
        $this->dealerValidationService = $dealerValidationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $level
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $level = 'verified')
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'data' => null
            ], 401);
        }

        // Validate dealer access based on required level
        $validation = $this->dealerValidationService->validateDealerAccess($user, 'access_studios');
        
        $hasAccess = match($level) {
            'basic' => $validation['validation_status']['is_dealer'],
            'active' => $validation['validation_status']['is_active_dealer'],
            'verified' => $validation['validation_status']['is_verified_dealer'],
            default => false
        };

        if (!$hasAccess) {
            // Log the access attempt
            $this->dealerValidationService->logDealerAccessAttempt($user, 'dealer_access', false);

            return response()->json([
                'success' => false,
                'message' => $this->getAccessDeniedMessage($level, $validation),
                'data' => [
                    'required_level' => $level,
                    'current_status' => $validation['validation_status']['status'],
                    'missing_requirements' => $validation['validation_status']['missing_requirements'],
                    'validation_details' => $validation['validation_status']
                ]
            ], 403);
        }

        // Log successful access
        $this->dealerValidationService->logDealerAccessAttempt($user, 'dealer_access', true);

        return $next($request);
    }

    /**
     * Get appropriate access denied message
     */
    protected function getAccessDeniedMessage(string $level, array $validation): string
    {
        $status = $validation['validation_status']['status'];
        $missing = $validation['validation_status']['missing_requirements'];

        $baseMessage = match($level) {
            'basic' => 'You must be a dealer to access this feature.',
            'active' => 'You must be an active dealer to access this feature.',
            'verified' => 'You must be a verified dealer to access this feature (documents and business info required).',
            default => 'Dealer verification required.'
        };

        if (!empty($missing)) {
            $baseMessage .= ' Missing requirements: ' . implode(', ', $missing);
        }

        return $baseMessage;
    }
}
