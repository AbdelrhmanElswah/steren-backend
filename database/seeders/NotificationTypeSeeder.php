<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notificationTypes = [
            [
                'name' => 'listing_approved',
                'display_name' => 'Listing Approved',
                'description' => 'Your car listing has been approved and is now live',
                'icon' => 'check_circle',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Listing Approved!',
                    'body' => 'Your {{car_name}} listing is now live and visible to buyers.'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 1,
            ],
            [
                'name' => 'listing_rejected',
                'display_name' => 'Listing Rejected',
                'description' => 'Your car listing has been rejected',
                'icon' => 'cancel',
                'color' => '#EF4444',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Listing Rejected',
                    'body' => 'Your {{car_name}} listing was rejected. Reason: {{reason}}'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 2,
            ],
            [
                'name' => 'price_drop',
                'display_name' => 'Price Drop Alert',
                'description' => 'A car in your watchlist has dropped in price',
                'icon' => 'trending_down',
                'color' => '#F59E0B',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Price Drop Alert!',
                    'body' => '{{car_name}} price dropped from {{old_price}} to {{new_price}}'
                ],
                'channels' => ['push'],
                'sort_order' => 3,
            ],
            [
                'name' => 'new_match',
                'display_name' => 'New Match Found',
                'description' => 'A new car matching your saved search has been listed',
                'icon' => 'notifications_active',
                'color' => '#3B82F6',
                'is_active' => true,
                'default_template' => [
                    'title' => 'New Match Found!',
                    'body' => 'A new {{car_name}} matching your search "{{search_title}}" is available'
                ],
                'channels' => ['push'],
                'sort_order' => 4,
            ],
            [
                'name' => 'message_received',
                'display_name' => 'Message Received',
                'description' => 'You have received a new message about your listing',
                'icon' => 'mail',
                'color' => '#8B5CF6',
                'is_active' => true,
                'default_template' => [
                    'title' => 'New Message',
                    'body' => 'You have a new message about {{car_name}}'
                ],
                'channels' => ['push'],
                'sort_order' => 5,
            ],
            [
                'name' => 'offer_received',
                'display_name' => 'Offer Received',
                'description' => 'You have received an offer on your listing',
                'icon' => 'local_offer',
                'color' => '#EC4899',
                'is_active' => true,
                'default_template' => [
                    'title' => 'New Offer Received',
                    'body' => 'You received an offer of {{offer_amount}} for {{car_name}}'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 6,
            ],
            [
                'name' => 'listing_expiring',
                'display_name' => 'Listing Expiring Soon',
                'description' => 'Your listing will expire soon',
                'icon' => 'schedule',
                'color' => '#F59E0B',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Listing Expiring Soon',
                    'body' => 'Your {{car_name}} listing expires in {{days}} days'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 7,
            ],
            [
                'name' => 'boost_activated',
                'display_name' => 'Boost Activated',
                'description' => 'Your listing boost has been activated',
                'icon' => 'rocket_launch',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Boost Activated!',
                    'body' => 'Your {{car_name}} listing is now boosted for {{duration}}'
                ],
                'channels' => ['push'],
                'sort_order' => 8,
            ],
            [
                'name' => 'boost_expired',
                'display_name' => 'Boost Expired',
                'description' => 'Your listing boost has expired',
                'icon' => 'timer_off',
                'color' => '#6B7280',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Boost Expired',
                    'body' => 'The boost for {{car_name}} has expired'
                ],
                'channels' => ['push'],
                'sort_order' => 9,
            ],
            [
                'name' => 'subscription_expiring',
                'display_name' => 'Subscription Expiring',
                'description' => 'Your subscription is about to expire',
                'icon' => 'card_membership',
                'color' => '#F59E0B',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Subscription Expiring',
                    'body' => 'Your {{plan_name}} subscription expires in {{days}} days'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 10,
            ],
            [
                'name' => 'payment_successful',
                'display_name' => 'Payment Successful',
                'description' => 'Your payment was processed successfully',
                'icon' => 'check_circle',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Payment Successful',
                    'body' => 'Your payment of {{amount}} was processed successfully'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 11,
            ],
            [
                'name' => 'payment_failed',
                'display_name' => 'Payment Failed',
                'description' => 'Your payment could not be processed',
                'icon' => 'error',
                'color' => '#EF4444',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Payment Failed',
                    'body' => 'Your payment of {{amount}} could not be processed'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 12,
            ],
        ];

        foreach ($notificationTypes as $type) {
            DB::table('notification_types')->insert([
                'name' => $type['name'],
                'display_name' => $type['display_name'],
                'description' => $type['description'],
                'icon' => $type['icon'],
                'color' => $type['color'],
                'is_active' => $type['is_active'],
                'default_template' => json_encode($type['default_template']),
                'channels' => json_encode($type['channels']),
                'sort_order' => $type['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
