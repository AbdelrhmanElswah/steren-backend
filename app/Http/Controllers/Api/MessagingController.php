<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsRequest;
use App\Http\Requests\SendWhatsAppRequest;
use App\Http\Requests\SendBulkMessageRequest;
use App\Services\StandingTechService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    use ApiResponse;

    protected StandingTechService $messagingService;

    public function __construct(StandingTechService $messagingService)
    {
        $this->messagingService = $messagingService;
    }

    /**
     * Send SMS message
     *
     * @param SendSmsRequest $request
     * @return JsonResponse
     */
    public function sendSMS(SendSmsRequest $request): JsonResponse
    {
        try {
            $result = $this->messagingService->sendSMS(
                $request->input('recipient'),
                $request->input('message'),
                $request->input('sender_id'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'SMS sent successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send WhatsApp message
     *
     * @param SendWhatsAppRequest $request
     * @return JsonResponse
     */
    public function sendWhatsApp(SendWhatsAppRequest $request): JsonResponse
    {
        try {
            $result = $this->messagingService->sendWhatsApp(
                $request->input('recipient'),
                $request->input('message'),
                $request->input('sender_id'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'WhatsApp message sent successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send notification (SMS or WhatsApp)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $request->validate([
            'recipient' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'string', 'in:sms,whatsapp'],
            'sender_id' => ['nullable', 'string', 'max:11'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ]);

        try {
            $result = $this->messagingService->sendNotification(
                $request->input('recipient'),
                $request->input('message'),
                $request->input('type'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'Notification sent successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send OTP via SMS
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendOTP(Request $request): JsonResponse
    {
        $request->validate([
            'recipient' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ]);

        try {
            $result = $this->messagingService->sendOTP(
                $request->input('recipient'),
                $request->input('otp'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'OTP sent successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send OTP via WhatsApp
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendOTPWhatsApp(Request $request): JsonResponse
    {
        $request->validate([
            'recipient' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'otp' => ['required', 'string', 'regex:/^[0-9]{4,6}$/'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ]);

        try {
            $result = $this->messagingService->sendOTPWhatsApp(
                $request->input('recipient'),
                $request->input('otp'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'OTP sent via WhatsApp successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send verification code
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendVerificationCode(Request $request): JsonResponse
    {
        $request->validate([
            'recipient' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
            'code' => ['required', 'string', 'max:10'],
            'type' => ['required', 'string', 'in:sms,whatsapp'],
            'lang' => ['nullable', 'string', 'in:en,ar'],
        ]);

        try {
            $result = $this->messagingService->sendVerificationCode(
                $request->input('recipient'),
                $request->input('code'),
                $request->input('type'),
                $request->input('lang', 'en')
            );

            return $this->successResponse(
                $result,
                'Verification code sent successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Send bulk messages
     *
     * @param SendBulkMessageRequest $request
     * @return JsonResponse
     */
    public function sendBulk(SendBulkMessageRequest $request): JsonResponse
    {
        try {
            $results = $this->messagingService->sendBulk(
                $request->input('recipients'),
                $request->input('message'),
                $request->input('type'),
                $request->input('lang', 'en')
            );

            $successCount = collect($results)->where('success', true)->count();
            $failureCount = collect($results)->where('success', false)->count();

            return $this->successResponse([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failureCount,
                ],
            ], "Bulk messages processed: {$successCount} sent, {$failureCount} failed");
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get service status
     *
     * @return JsonResponse
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->messagingService->getStatus();
            
            return $this->successResponse(
                $status,
                'Service status retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                500
            );
        }
    }
}

