<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\StandingTechException;

class StandingTechService
{
    protected ?string $apiUrl;
    protected ?string $apiToken;
    protected ?string $defaultSenderId;
    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.standingtech.api_url');
        $this->apiToken = config('services.standingtech.api_token');
        $this->defaultSenderId = config('services.standingtech.sender_id');
        // Reduce timeout for registration to avoid blocking (default 10 seconds)
        $this->timeout = config('services.standingtech.timeout', 10);

        // Validate configuration
        if (empty($this->apiToken)) {
            Log::warning('StandingTech API token is not configured. Set STANDINGTECH_API_TOKEN in your .env file.');
        }
    }

    /**
     * Send SMS message
     *
     * @param string $recipient Phone number with country code (e.g., 9647502171212)
     * @param string $message The message content
     * @param string|null $senderId Optional sender ID
     * @param string $lang Language code (en, ar, etc.)
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendSMS(string $recipient, string $message, ?string $senderId = null, string $lang = 'en'): array
    {
        $data = [
            'recipient' => $this->formatPhoneNumber($recipient),
            'type' => 'sms',
            'message' => $message,
            'lang' => $lang,
        ];

        // Only include sender_id if explicitly provided or if default is set
        $senderIdToUse = $senderId ?? $this->defaultSenderId;
        if (!empty($senderIdToUse)) {
            $data['sender_id'] = $senderIdToUse;
        }

        return $this->send($data);
    }

    /**
     * Send WhatsApp message
     *
     * @param string $recipient Phone number with country code (e.g., 9647502171212)
     * @param string $message The message content
     * @param string|null $senderId Optional sender ID
     * @param string $lang Language code (en, ar, etc.)
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendWhatsApp(string $recipient, string $message, ?string $senderId = null, string $lang = 'en'): array
    {
        $data = [
            'recipient' => $this->formatPhoneNumber($recipient),
            'type' => 'whatsapp',
            'message' => $message,
            'lang' => $lang,
        ];

        // Only include sender_id if explicitly provided or if default is set
        $senderIdToUse = $senderId ?? $this->defaultSenderId;
        if (!empty($senderIdToUse)) {
            $data['sender_id'] = $senderIdToUse;
        }

        return $this->send($data);
    }

    /**
     * Send OTP via SMS
     *
     * @param string $recipient Phone number
     * @param string $otp OTP code
     * @param string $lang Language code
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendOTP(string $recipient, string $otp, string $lang = 'en'): array
    {
        $message = $this->getOTPMessage($otp, $lang);
        return $this->sendSMS($recipient, $message, null, $lang);
    }

    /**
     * Send OTP via WhatsApp
     *
     * @param string $recipient Phone number
     * @param string $otp OTP code
     * @param string $lang Language code
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendOTPWhatsApp(string $recipient, string $otp, string $lang = 'en'): array
    {
        $message = $this->getOTPMessage($otp, $lang);
        return $this->sendWhatsApp($recipient, $message, null, $lang);
    }

    /**
     * Send verification code
     *
     * @param string $recipient Phone number
     * @param string $code Verification code
     * @param string $type sms or whatsapp
     * @param string $lang Language code
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendVerificationCode(string $recipient, string $code, string $type = 'sms', string $lang = 'en'): array
    {
        $message = $this->getVerificationMessage($code, $lang);
        
        if ($type === 'whatsapp') {
            return $this->sendWhatsApp($recipient, $message, null, $lang);
        }
        
        return $this->sendSMS($recipient, $message, null, $lang);
    }

    /**
     * Send notification message
     *
     * @param string $recipient Phone number
     * @param string $message Message content
     * @param string $type sms or whatsapp
     * @param string $lang Language code
     * @return array Response from API
     * @throws StandingTechException
     */
    public function sendNotification(string $recipient, string $message, string $type = 'sms', string $lang = 'en'): array
    {
        if ($type === 'whatsapp') {
            return $this->sendWhatsApp($recipient, $message, null, $lang);
        }
        
        return $this->sendSMS($recipient, $message, null, $lang);
    }

    /**
     * Send bulk messages
     *
     * @param array $recipients Array of phone numbers
     * @param string $message Message content
     * @param string $type sms or whatsapp
     * @param string $lang Language code
     * @return array Array of responses
     */
    public function sendBulk(array $recipients, string $message, string $type = 'sms', string $lang = 'en'): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            try {
                $results[] = [
                    'recipient' => $recipient,
                    'success' => true,
                    'response' => $this->sendNotification($recipient, $message, $type, $lang),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'recipient' => $recipient,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Core send method
     *
     * @param array $data Message data
     * @return array Response from API
     * @throws StandingTechException
     */
    protected function send(array $data): array
    {
        // Check if service is configured
        if (!$this->isConfigured()) {
            throw new StandingTechException(
                'StandingTech service is not configured. Please set STANDINGTECH_API_TOKEN in your .env file.',
                500
            );
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->apiUrl, $data);

            if ($response->successful()) {
                Log::info('StandingTech message sent successfully', [
                    'recipient' => $data['recipient'],
                    'type' => $data['type'],
                ]);
                
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status_code' => $response->status(),
                ];
            }

            // Get detailed error information
            $responseBody = $response->json();
            
            // Handle different error response formats
            $errorMessage = 'Failed to send message';
            if (isset($responseBody['message'])) {
                $errorMessage = is_array($responseBody['message']) 
                    ? json_encode($responseBody['message']) 
                    : $responseBody['message'];
            } elseif (isset($responseBody['error'])) {
                $errorMessage = is_array($responseBody['error']) 
                    ? json_encode($responseBody['error']) 
                    : $responseBody['error'];
            }
            
            $errorDetails = $responseBody['errors'] ?? $responseBody['details'] ?? null;
            
            // Build detailed error message
            $fullErrorMessage = $errorMessage;
            if ($errorDetails) {
                $fullErrorMessage .= ' | Details: ' . json_encode($errorDetails);
            }
            
            Log::error('StandingTech API error', [
                'status' => $response->status(),
                'response' => $responseBody,
                'request_data' => $data,
                'headers' => [
                    'Authorization' => 'Bearer ' . substr($this->apiToken ?? '', 0, 20) . '...',
                    'api_url' => $this->apiUrl,
                ],
            ]);

            throw new StandingTechException(
                $fullErrorMessage . ' (HTTP ' . $response->status() . ')',
                $response->status()
            );
        } catch (StandingTechException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('StandingTech service exception', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            // Get a cleaner error message
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'Array to string conversion') !== false) {
                $errorMsg = 'Invalid response format from API. Check logs for details.';
            }

            throw new StandingTechException(
                'Failed to send message: ' . $errorMsg,
                500,
                $e
            );
        }
    }

    /**
     * Format phone number (remove + and spaces)
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * Get OTP message template
     *
     * @param string $otp
     * @param string $lang
     * @return string
     */
    protected function getOTPMessage(string $otp, string $lang): string
    {
        if ($lang === 'ar') {
            return "رمز التحقق الخاص بك هو: {$otp}. صالح لمدة 10 دقائق.";
        }
        
        return "Your verification code is: {$otp}. Valid for 10 minutes.";
    }

    /**
     * Get verification message template
     *
     * @param string $code
     * @param string $lang
     * @return string
     */
    protected function getVerificationMessage(string $code, string $lang): string
    {
        if ($lang === 'ar') {
            return "رمز التحقق من Steren: {$code}";
        }
        
        return "Your Steren verification code: {$code}";
    }

    /**
     * Check if service is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiToken);
    }

    /**
     * Get service status
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'api_url' => $this->apiUrl,
            'sender_id' => $this->defaultSenderId,
        ];
    }
}

