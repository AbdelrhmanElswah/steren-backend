<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthService $authService
    ) {}
    
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->authService->socialAuth(
                $validated['provider'],
                $validated['firebase_token'],
                [
                    'full_name' => $validated['full_name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'profile_picture' => $validated['profile_picture'] ?? null,
                ]
            );

            if (isset($result['is_pending']) && $result['is_pending']) {
                return $this->successResponse([
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                    'is_pending' => true,
                    'message' => $result['message'],
                    'firebase_user_info' => $result['firebase_user_info'],
                ], 'Account created but requires completion');
            }

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'is_pending' => false,
                'client_integration' => $result['client_integration'],
                'firebase_user_info' => $result['firebase_user_info'],
            ], 'Social authentication successful');

        } catch (\Exception $e) {
            Log::error('SocialAuthController: Social authentication failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->errorResponse('Social authentication failed: ' . $e->getMessage(), 401);
        }
    }
}
