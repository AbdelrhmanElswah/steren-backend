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
                'name' => 'user_created',
                'display_name' => 'Welcome to Steren',
                'description' => 'Welcome notification when user registers',
                'icon' => 'waving_hand',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Welcome to Steren!',
                    'body' => 'Welcome {{user_name}}! We\'re excited to have you join our community. Start exploring amazing cars or list your own!'
                ],
                'channels' => ['push'],
                'sort_order' => 0,
            ],
            [
                'name' => 'listing_created',
                'display_name' => 'Listing Created',
                'description' => 'Your car listing has been created successfully',
                'icon' => 'add_circle',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Listing Created Successfully!',
                    'body' => 'Your {{car_name}} listing has been created and is pending approval.'
                ],
                'channels' => ['push'],
                'sort_order' => 1,
            ],
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
                'sort_order' => 2,
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
                'sort_order' => 3,
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
                'sort_order' => 4,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
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
                'sort_order' => 13,
            ],
            [
                'name' => 'dealer_application_submitted',
                'display_name' => 'Dealer Application Submitted',
                'description' => 'Your dealer application has been submitted successfully',
                'icon' => 'send',
                'color' => '#3B82F6',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Application Submitted!',
                    'body' => 'Your dealer application for {{company_name}} has been submitted and is pending review.'
                ],
                'channels' => ['push'],
                'sort_order' => 14,
            ],
            [
                'name' => 'dealer_application_approved',
                'display_name' => 'Dealer Application Approved',
                'description' => 'Your dealer application has been approved',
                'icon' => 'check_circle',
                'color' => '#10B981',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Dealer Application Approved!',
                    'body' => 'Congratulations! Your dealer application for {{company_name}} has been approved. You can now access dealer features.'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 15,
            ],
            [
                'name' => 'dealer_application_rejected',
                'display_name' => 'Dealer Application Rejected',
                'description' => 'Your dealer application has been rejected',
                'icon' => 'cancel',
                'color' => '#EF4444',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Dealer Application Rejected',
                    'body' => 'Your dealer application for {{company_name}} was not approved. Reason: {{reason}}'
                ],
                'channels' => ['push', 'email'],
                'sort_order' => 16,
            ],
            [
                'name' => 'search_saved',
                'display_name' => 'Search Saved',
                'description' => 'Your search has been saved successfully',
                'icon' => 'bookmark',
                'color' => '#3B82F6',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Search Saved Successfully!',
                    'body' => 'Your search "{{search_title}}" has been saved. You can find it in your saved searches.'
                ],
                'channels' => ['push'],
                'sort_order' => 16,
            ],
            [
                'name' => 'watchlist_added',
                'display_name' => 'Added to Watch List',
                'description' => 'A car has been added to your watch list',
                'icon' => 'favorite',
                'color' => '#EC4899',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Added to Watch List!',
                    'body' => '{{car_name}} has been added to your watch list.'
                ],
                'channels' => ['push'],
                'sort_order' => 17,
            ],
            [
                'name' => 'listing_updated',
                'display_name' => 'Listing Updated',
                'description' => 'Your car listing has been updated successfully',
                'icon' => 'edit',
                'color' => '#3B82F6',
                'is_active' => true,
                'default_template' => [
                    'title' => 'Listing Updated Successfully!',
                    'body' => 'Your {{car_name}} listing has been updated and is being reviewed again.'
                ],
                'channels' => ['push'],
                'sort_order' => 18,
            ],
        ];

        foreach ($notificationTypes as $type) {
            DB::table('notification_types')->updateOrInsert(
                ['name' => $type['name']], // Match by unique name
                [
                    'display_name' => $type['display_name'],
                    'description' => $type['description'],
                    'icon' => $type['icon'],
                    'color' => $type['color'],
                    'is_active' => $type['is_active'],
                    'default_template' => json_encode($type['default_template']),
                    'channels' => json_encode($type['channels']),
                    'sort_order' => $type['sort_order'],
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'), // Preserve original created_at if exists
                ]
            );
        }
    }
}
