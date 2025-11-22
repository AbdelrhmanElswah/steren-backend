<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'FIB',
                'display_name' => 'FIB',
                'type' => 'bank',
                'gateway' => 'FIB',
                'description' => 'Fast Iraqi Bank payment method',
                'logo_url' => '/images/payment-methods/fib.png',
                'supported_currencies' => ['IQD', 'USD'],
                'min_amount' => 1000.00,
                'max_amount' => 10000000.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Zain',
                'display_name' => 'Zain',
                'type' => 'mobile_money',
                'gateway' => 'Zain',
                'description' => 'Zain mobile money payment',
                'logo_url' => '/images/payment-methods/zain.png',
                'supported_currencies' => ['IQD'],
                'min_amount' => 1000.00,
                'max_amount' => 5000000.00,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Card',
                'display_name' => 'Card',
                'type' => 'card',
                'gateway' => 'Stripe',
                'description' => 'Credit/Debit card payment',
                'logo_url' => '/images/payment-methods/card.png',
                'supported_currencies' => ['IQD', 'USD', 'EUR'],
                'min_amount' => 1000.00,
                'max_amount' => 50000000.00,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mastercard',
                'display_name' => 'Mastercard',
                'type' => 'card',
                'gateway' => 'Stripe',
                'description' => 'Mastercard payment',
                'logo_url' => '/images/payment-methods/mastercard.png',
                'supported_currencies' => ['IQD', 'USD', 'EUR'],
                'min_amount' => 1000.00,
                'max_amount' => 50000000.00,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Visa',
                'display_name' => 'Visa',
                'type' => 'card',
                'gateway' => 'Stripe',
                'description' => 'Visa payment',
                'logo_url' => '/images/payment-methods/visa.png',
                'supported_currencies' => ['IQD', 'USD', 'EUR'],
                'min_amount' => 1000.00,
                'max_amount' => 50000000.00,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'PayPal',
                'display_name' => 'PayPal',
                'type' => 'digital_wallet',
                'gateway' => 'PayPal',
                'description' => 'PayPal digital wallet payment',
                'logo_url' => '/images/payment-methods/paypal.png',
                'supported_currencies' => ['USD', 'EUR'],
                'min_amount' => 1.00,
                'max_amount' => 10000.00,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Apple Pay',
                'display_name' => 'Apple Pay',
                'type' => 'digital_wallet',
                'gateway' => 'Apple Pay',
                'description' => 'Apple Pay mobile payment',
                'logo_url' => '/images/payment-methods/apple-pay.png',
                'supported_currencies' => ['USD', 'EUR'],
                'min_amount' => 0.01,
                'max_amount' => 10000.00,
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['name' => $method['name']],
                $method
            );
        }
    }
}
