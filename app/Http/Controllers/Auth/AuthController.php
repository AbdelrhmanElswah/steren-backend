<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\StartPasswordResetRequest;
use App\Http\Requests\Auth\CompletePasswordResetRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'otp_sent' => $result['otp_sent'],
        ], 'Registration initiated');
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $result = $this->authService->login($validated['identifier'], $validated['password']);

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
        $result = $this->authService->verifyOtp($validated['phone'], $validated['code']);

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Verified');
    }

    public function me(Request $request)
    {
        return $this->resourceResponse(new UserResource($request->user()));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return $this->successResponse(null, 'Logged out');
    }

    public function startPasswordReset(StartPasswordResetRequest $request)
    {
        $result = $this->authService->startPasswordReset($request->validated()['phone']);
        return $this->successResponse(['sent' => $result['sent']], 'Reset code sent');
    }

    public function completePasswordReset(CompletePasswordResetRequest $request)
    {
        $validated = $request->validated();
        $this->authService->completePasswordReset($validated['phone'], $validated['code'], $validated['password']);
        return $this->successResponse(null, 'Password updated');
    }
}


