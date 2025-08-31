<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'currency',
        'is_active',
        'payment_methods',
        'last_transaction_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'payment_methods' => 'array',
        'last_transaction_at' => 'datetime',
    ];

    /**
     * Get the user that owns this wallet
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get transactions for this wallet
     */
    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get recent transactions
     */
    public function recentTransactions($limit = 10)
    {
        return $this->transactions()->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute()
    {
        return $this->currency . ' ' . number_format($this->balance, 2);
    }

    /**
     * Check if wallet has sufficient balance
     */
    public function hasSufficientBalance($amount)
    {
        return $this->balance >= $amount;
    }

    /**
     * Add funds to wallet
     */
    public function addFunds($amount)
    {
        $this->increment('balance', $amount);
        $this->update(['last_transaction_at' => now()]);
    }

    /**
     * Deduct funds from wallet
     */
    public function deductFunds($amount)
    {
        if ($this->hasSufficientBalance($amount)) {
            $this->decrement('balance', $amount);
            $this->update(['last_transaction_at' => now()]);
            return true;
        }
        return false;
    }

    /**
     * Add funds to wallet with transaction record
     */
    public function addFundsWithTransaction($amount, $paymentMethod = null, $paymentGateway = null, $referenceId = null, $description = null)
    {
        $balanceBefore = $this->balance;
        $this->addFunds($amount);
        $balanceAfter = $this->balance;

        // Create transaction record
        $this->transactions()->create([
            'transaction_type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => $this->currency,
            'payment_method' => $paymentMethod,
            'payment_gateway' => $paymentGateway,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'description' => $description,
            'processed_at' => now(),
        ]);

        return true;
    }

    /**
     * Deduct funds from wallet with transaction record
     */
    public function deductFundsWithTransaction($amount, $description = null)
    {
        if (!$this->hasSufficientBalance($amount)) {
            return false;
        }

        $balanceBefore = $this->balance;
        $this->deductFunds($amount);
        $balanceAfter = $this->balance;

        // Create transaction record
        $this->transactions()->create([
            'transaction_type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency' => $this->currency,
            'status' => 'completed',
            'description' => $description,
            'processed_at' => now(),
        ]);

        return true;
    }
}
