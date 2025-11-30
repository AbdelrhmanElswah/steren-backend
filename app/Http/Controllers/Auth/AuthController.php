<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\OdooIntegrationResource;
use App\Services\AuthService;
use App\Services\OdooService;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\StartPasswordResetRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Http\Requests\Auth\CompletePasswordResetRequest;
use App\Http\Requests\Auth\UpdatePartnerRequest;
use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooPartnerCreationException;
use App\Exceptions\OdooOtpVerificationException;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthService $authService,
        private OdooService $odooService
    ) {
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        // Load only necessary relationships to avoid N+1 queries
        $result['user']->loadMissing('profilePictureMedia');

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'otp_sent' => $result['otp_sent'],
            'token' => $result['token'],
        ], 'Registration initiated');
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $result = $this->authService->login($validated['identifier'], $validated['password']);

        $result['user']->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Logged in');
    }

    public function sendOtp(SendOtpRequest $request)
    {
        $result = $this->authService->sendOtp($request->validated()['phone']);
        return $this->successResponse($result, 'OTP sent');
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        
        $clientData = [
            'full_name' => $user->full_name,
            'phone' => $user->phone,
            'email' => $user->email
        ];
        
        $result = $this->authService->verifyOtp($user, $validated['code'], $clientData);

        // Load dealer application data
        $result['user']->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'client_integration' => $result['client_integration'] ?? null,
        ], 'Verified');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['latestDealerApplication.status', 'latestDealerApplication.subscriptionPlan']);
        
        return $this->resourceResponse(new UserResource($user));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return $this->successResponse(null, 'Logged out');
    }

    public function logoutAll(Request $request)
    {
        $this->authService->logoutAll($request->user());
        return $this->successResponse(null, 'Logged out from all devices');
    }

    public function completePendingAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ]);

            $user = $request->user();
            
            if (!$user->is_pending) {
                return $this->errorResponse('Account is not pending completion', 400);
            }

            $result = $this->authService->completePendingAccount(
                $user->id,
                $validated['full_name'],
                $validated['phone']
            );

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'client_integration' => $result['client_integration'],
            ], 'Account completed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to complete account: ' . $e->getMessage(), 500);
        }
    }

    public function startPasswordReset(StartPasswordResetRequest $request)
    {
        $result = $this->authService->startPasswordReset($request->validated()['phone']);
        return $this->successResponse(['sent' => $result['sent']], 'Reset code sent');
    }

    public function verifyPasswordResetOtp(VerifyPasswordResetOtpRequest $request)
    {
        $validated = $request->validated();
        $result = $this->authService->verifyPasswordResetOtp($validated['phone'], $validated['code']);
        return $this->successResponse(['verified' => $result['verified']], 'OTP verified successfully');
    }

    public function completePasswordReset(CompletePasswordResetRequest $request)
    {
        $validated = $request->validated();
        $this->authService->completePasswordReset($validated['phone'], $validated['password']);
        return $this->successResponse(null, 'Password updated successfully');
    }

    public function getPartnerInfo(Request $request)
    {
        $user = $request->user();
        
        if (!$user->partner_id) {
            return $this->errorResponse('No partner ID found for this user', 404);
        }

        try {
            $result = $this->odooService->getPartnerInfo($user->partner_id);
            
            if ($result['success']) {
                return $this->successResponse(
                    new PartnerResource($result['partner']), 
                    'Partner information retrieved'
                );
            }

            return $this->errorResponse($result['message'], 400);
        } catch (OdooConnectionException $e) {
            return $this->serviceUnavailableResponse('Unable to retrieve partner information: ' . $e->getMessage());
        }
    }

    public function updatePartnerInfo(UpdatePartnerRequest $request)
    {
        $user = $request->user();
        
        if (!$user->partner_id) {
            return $this->errorResponse('No partner ID found for this user', 404);
        }

        $validated = $request->validated();

        try {
            $result = $this->odooService->updatePartner($user->partner_id, $validated);
            
            if ($result['success']) {
                $user->update($validated);
                return $this->successResponse(null, 'Partner information updated');
            }

            return $this->errorResponse($result['message'], 400);
        } catch (OdooConnectionException $e) {
            return $this->serviceUnavailableResponse('Unable to update partner information: ' . $e->getMessage());
        }
    }

}


