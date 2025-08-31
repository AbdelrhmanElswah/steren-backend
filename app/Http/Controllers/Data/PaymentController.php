<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\PaymentProcessing;
use App\Models\Wallet;
use App\Models\PaymentMethod;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponse;

    /**
     * Initiate payment
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:1000',
                'payment_type' => 'required|string',
                'payment_data' => 'nullable|array',
            ]);

            $paymentMethod = PaymentMethod::where('name', $request->payment_method)->first();
            if (!$paymentMethod || !$paymentMethod->is_active) {
                return $this->errorResponse('Invalid payment method', 400);
            }

            // Validate amount limits
            if (!$paymentMethod->isAmountValid($request->amount)) {
                return $this->errorResponse('Amount is outside the allowed limits for this payment method', 400);
            }

            // Create payment processing record
            $payment = PaymentProcessing::create([
                'transaction_id' => PaymentProcessing::generateTransactionId(),
                'user_id' => $user->id,
                'wallet_id' => $user->wallet?->id,
                'payment_method' => $request->payment_method,
                'payment_gateway' => $paymentMethod->gateway,
                'amount' => $request->amount,
                'currency' => 'IQD',
                'status' => 'pending',
                'payment_type' => $request->payment_type,
                'payment_data' => $request->payment_data,
            ]);

            // Generate OTP for verification
            $otpCode = $payment->generateOtp();

            $data = [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->formatted_amount,
                'payment_method' => $payment->payment_method,
                'requires_otp' => true,
                'otp_sent' => true,
                'message' => 'OTP sent to your phone number',
            ];

            return $this->successResponse($data, 'Payment initiated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initiate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify OTP and complete payment
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'payment_id' => 'required|exists:payment_processing,id',
                'otp_code' => 'required|string|size:4',
            ]);

            $payment = PaymentProcessing::where('id', $request->payment_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if (!$payment->isOtpValid()) {
                return $this->errorResponse('OTP is invalid or expired', 400);
            }

            // Verify OTP
            if (!$payment->verifyOtp($request->otp_code)) {
                return $this->errorResponse('Invalid OTP code', 400);
            }

            // Process payment based on type
            $result = $this->processPayment($payment);

            if (!$result['success']) {
                $payment->markAsFailed($result['reason']);
                
                $data = [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'status' => 'failed',
                    'failure_reason' => $result['reason'],
                    'timestamp' => $payment->created_at->format('M d, Y - H:i'),
                ];

                return $this->errorResponse($data, 'Payment failed: ' . $result['reason'], 400);
            }

            // Mark payment as completed
            $payment->markAsCompleted();

            $data = [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => 'completed',
                'amount_added' => $payment->formatted_amount,
                'updated_balance' => $user->wallet?->formatted_balance ?? '0.00 IQD',
                'timestamp' => $payment->processed_at->format('M d, Y - H:i'),
            ];

            return $this->successResponse($data, 'Payment completed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to verify OTP: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'payment_id' => 'required|exists:payment_processing,id',
            ]);

            $payment = PaymentProcessing::where('id', $request->payment_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($payment->isCompleted()) {
                return $this->errorResponse('Payment already completed', 400);
            }

            // Generate new OTP
            $otpCode = $payment->generateOtp();

            return $this->successResponse([
                'payment_id' => $payment->id,
                'otp_sent' => true,
                'message' => 'New OTP sent to your phone number',
            ], 'OTP resent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to resend OTP: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, $paymentId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $payment = PaymentProcessing::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $data = [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => $payment->formatted_amount,
                'payment_method' => $payment->payment_method,
                'payment_type' => $payment->payment_type_label,
                'created_at' => $payment->created_at->format('M d, Y - H:i'),
                'processed_at' => $payment->processed_at?->format('M d, Y - H:i'),
                'failure_reason' => $payment->failure_reason,
            ];

            return $this->successResponse($data, 'Payment status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get payment status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process payment based on type
     */
    private function processPayment(PaymentProcessing $payment)
    {
        try {
            switch ($payment->payment_type) {
                case 'wallet_topup':
                    return $this->processWalletTopup($payment);
                    
                case 'subscription':
                    return $this->processSubscription($payment);
                    
                case 'ad_boost':
                    return $this->processAdBoost($payment);
                    
                default:
                    return ['success' => false, 'reason' => 'Unknown payment type'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Process wallet topup
     */
    private function processWalletTopup(PaymentProcessing $payment)
    {
        $user = $payment->user;
        
        // Ensure wallet exists
        if (!$user->wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
                'currency' => 'IQD',
            ]);
        } else {
            $wallet = $user->wallet;
        }

        // Add funds to wallet
        $wallet->addFundsWithTransaction(
            $payment->amount,
            $payment->payment_method,
            $payment->payment_gateway,
            $payment->transaction_id,
            'Wallet topup via ' . $payment->payment_method
        );

        return ['success' => true];
    }

    /**
     * Process subscription payment
     */
    private function processSubscription(PaymentProcessing $payment)
    {
        // This would integrate with subscription system
        // For now, just return success
        return ['success' => true];
    }

    /**
     * Process ad boost payment
     */
    private function processAdBoost(PaymentProcessing $payment)
    {
        // This would integrate with ad boost system
        // For now, just return success
        return ['success' => true];
    }
}
