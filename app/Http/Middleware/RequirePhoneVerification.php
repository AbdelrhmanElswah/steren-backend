<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePhoneVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        if (is_null($user->phone_verified_at)) {
            $allowedRoutes = [
                'api/auth/verify-otp',
                'api/auth/logout',
                'api/user', 
            ];
            
            $currentRoute = $request->route()?->getName() ?? $request->path();
            
            $isAllowed = false;
            foreach ($allowedRoutes as $allowedRoute) {
                if (str_contains($currentRoute, $allowedRoute) || str_contains($request->path(), $allowedRoute)) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone verification required',
                    'error' => 'PHONE_VERIFICATION_REQUIRED',
                    'phone_verified' => false
                ], 403);
            }
        }
        
        return $next($request);
    }
}
