<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContactMethod;

class ContactMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contactMethods = [
            [
                'name' => 'SMS',
                'icon' => 'sms',
                'color' => '#4CAF50',
                'action_type' => 'sms',
                'action_url' => 'sms:{phone}?body={message}',
                'requires_phone' => true,
                'requires_email' => false,
                'instructions' => 'Send a text message to the seller',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'E-mail',
                'icon' => 'email',
                'color' => '#2196F3',
                'action_type' => 'email',
                'action_url' => 'mailto:{email}?subject=Car Inquiry&body={message}',
                'requires_phone' => false,
                'requires_email' => true,
                'instructions' => 'Send an email to the seller',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Call',
                'icon' => 'phone',
                'color' => '#FF9800',
                'action_type' => 'call',
                'action_url' => 'tel:{phone}',
                'requires_phone' => true,
                'requires_email' => false,
                'instructions' => 'Call the seller directly',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'WhatsApp',
                'icon' => 'whatsapp',
                'color' => '#25D366',
                'action_type' => 'whatsapp',
                'action_url' => 'https://wa.me/{phone}?text={message}',
                'requires_phone' => true,
                'requires_email' => false,
                'instructions' => 'Contact the seller via WhatsApp',
                'is_active' => true,
                'display_order' => 4,
            ],
        ];

        foreach ($contactMethods as $method) {
            ContactMethod::create($method);
        }
    }
}
