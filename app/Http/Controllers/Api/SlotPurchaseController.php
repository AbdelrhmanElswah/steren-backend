<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotPurchase;
use App\Models\UserSlot;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class SlotPurchaseController extends Controller
{
    use ApiResponse;

    /**
     * Get user's slot information
     */
    public function getSlots(Request $request)
    {
        try {
            $user = $request->user();
            $userSlot = $user->userSlot;

            // Create slot record if doesn't exist
            if (!$userSlot) {
                $userSlot = UserSlot::create([
                    'user_id' => $user->id,
                    'total_slots' => 1,
                    'consumed_slots' => 0,
                    'available_slots' => 1,
                    'purchased_slots' => 0,
                    'has_monthly_free_slot' => true,
                    'last_free_slot_reset_at' => now(),
                    'next_free_slot_reset_at' => now()->addMonth()->startOfMonth(),
                ]);
            }

            // Check if monthly free slot needs reset
            if ($userSlot->shouldResetMonthlyFreeSlot()) {
                $userSlot->resetMonthlyFreeSlot();
                $userSlot->refresh();
            }

            return $this->successResponse([
                'slots' => $userSlot->slot_breakdown,
                'status' => $userSlot->status,
            ], 'Slot information retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve slot information: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Purchase additional slots
     */
    public function purchaseSlots(Request $request)
    {
        try {
            $validated = $request->validate([
                'slots_count' => 'required|integer|min:1|max:100',
                'amount_paid' => 'required|numeric|min:0',
                'currency' => 'required|string|size:3',
                'currency_symbol' => 'nullable|string|max:5',
                'payment_method' => 'required|string|in:credit_card,paypal,wallet,bank_transfer',
                'transaction_id' => 'nullable|string|max:255',
                'payment_details' => 'nullable|array',
            ]);

            DB::beginTransaction();

            // Create slot purchase record
            $slotPurchase = SlotPurchase::create([
                'user_id' => $request->user()->id,
                'slots_purchased' => $validated['slots_count'],
                'amount_paid' => $validated['amount_paid'],
                'currency' => $validated['currency'],
                'currency_symbol' => $validated['currency_symbol'] ?? '$',
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $validated['transaction_id'] ?? null,
                'payment_status' => 'completed', // You can change this to 'pending' if payment verification is needed
                'payment_details' => $validated['payment_details'] ?? null,
                'purchased_at' => now(),
            ]);

            // Mark as completed to add slots to user
            $slotPurchase->markAsCompleted();

            DB::commit();

            // Get updated slot info
            $userSlot = $request->user()->userSlot;
            $userSlot->refresh();

            return $this->createdResponse([
                'purchase' => $slotPurchase,
                'slots' => $userSlot->slot_breakdown,
                'message' => "Successfully purchased {$validated['slots_count']} slot(s)!"
            ], 'Slots purchased successfully');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to purchase slots: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get user's slot purchase history
     */
    public function getPurchaseHistory(Request $request)
    {
        try {
            $purchases = $request->user()
                ->slotPurchases()
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return $this->successResponse([
                'purchases' => $purchases
            ], 'Purchase history retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve purchase history: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get slot pricing information
     */
    public function getPricing(Request $request)
    {
        try {
            // You can make this dynamic or fetch from database
            $pricing = [
                [
                    'slots' => 5,
                    'price' => 10,
                    'price_per_slot' => 2,
                    'currency' => 'USD',
                    'currency_symbol' => '$',
                    'is_popular' => false,
                ],
                [
                    'slots' => 10,
                    'price' => 15,
                    'price_per_slot' => 1.5,
                    'currency' => 'USD',
                    'currency_symbol' => '$',
                    'is_popular' => true,
                    'savings' => '25%'
                ],
                [
                    'slots' => 25,
                    'price' => 30,
                    'price_per_slot' => 1.2,
                    'currency' => 'USD',
                    'currency_symbol' => '$',
                    'is_popular' => false,
                    'savings' => '40%'
                ],
            ];

            return $this->successResponse([
                'pricing' => $pricing
            ], 'Pricing information retrieved successfully');

        } catch (Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve pricing: ' . $e->getMessage(),
                500
            );
        }
    }
}

