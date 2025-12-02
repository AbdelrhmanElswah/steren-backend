<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'odoo.tracking' => \App\Http\Middleware\OdooRequestTracking::class,
            'phone.verified' => \App\Http\Middleware\RequirePhoneVerification::class,
            'verify.dealer' => \App\Http\Middleware\VerifyDealer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes always return JSON, even for authentication failures
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'UNAUTHENTICATED'
                ], 401);
            }
        });

        // Handle authorization failures (from FormRequest authorize() returning false)
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'UNAUTHENTICATED'
                ], 401);
            }
        });

        // Ensure 404 errors for API routes return JSON
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Route not found',
                    'error' => 'NOT_FOUND'
                ], 404);
            }
        });
    })->create();
