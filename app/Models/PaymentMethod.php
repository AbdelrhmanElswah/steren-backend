<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'type',
        'gateway',
        'description',
        'logo_url',
        'supported_currencies',
        'min_amount',
        'max_amount',
        'is_active',
        'sort_order',
        'configuration',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'configuration' => 'array',
    ];

    /**
     * Scope to get only active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Scope to get payment methods by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get payment methods that support a currency
     */
    public function scopeSupportsCurrency($query, $currency)
    {
        return $query->whereJsonContains('supported_currencies', $currency);
    }

    /**
     * Check if payment method supports a currency
     */
    public function supportsCurrency($currency)
    {
        return in_array($currency, $this->supported_currencies ?? []);
    }

    /**
     * Check if amount is within limits
     */
    public function isAmountValid($amount)
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    /**
     * Get formatted min amount
     */
    public function getFormattedMinAmountAttribute()
    {
        if (!$this->min_amount) {
            return null;
        }

        return 'IQD ' . number_format($this->min_amount, 2);
    }

    /**
     * Get formatted max amount
     */
    public function getFormattedMaxAmountAttribute()
    {
        if (!$this->max_amount) {
            return null;
        }

        return 'IQD ' . number_format($this->max_amount, 2);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }
}
