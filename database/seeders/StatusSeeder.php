<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            // General Statuses
            [
                'name' => 'active',
                'display_name' => 'Active',
                'type' => 'general',
                'description' => 'Active status',
                'color' => '#10B981',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'inactive',
                'display_name' => 'Inactive',
                'type' => 'general',
                'description' => 'Inactive status',
                'color' => '#6B7280',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'pending',
                'display_name' => 'Pending',
                'type' => 'general',
                'description' => 'Pending status',
                'color' => '#F59E0B',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'approved',
                'display_name' => 'Approved',
                'type' => 'general',
                'description' => 'Approved status',
                'color' => '#10B981',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'rejected',
                'display_name' => 'Rejected',
                'type' => 'general',
                'description' => 'Rejected status',
                'color' => '#EF4444',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'under_review',
                'display_name' => 'Under Review',
                'type' => 'general',
                'description' => 'Under review status',
                'color' => '#3B82F6',
                'sort_order' => 6,
                'is_active' => true,
            ],

            // Dealer Application Statuses
            [
                'name' => 'pending',
                'display_name' => 'Pending',
                'type' => 'dealer_application',
                'description' => 'Dealer application is pending review',
                'color' => '#F59E0B',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'under_review',
                'display_name' => 'Under Review',
                'type' => 'dealer_application',
                'description' => 'Dealer application is under review',
                'color' => '#3B82F6',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'approved',
                'display_name' => 'Approved',
                'type' => 'dealer_application',
                'description' => 'Dealer application has been approved',
                'color' => '#10B981',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'rejected',
                'display_name' => 'Rejected',
                'type' => 'dealer_application',
                'description' => 'Dealer application has been rejected',
                'color' => '#EF4444',
                'sort_order' => 4,
                'is_active' => true,
            ],

            // Car Listing Statuses
            [
                'name' => 'draft',
                'display_name' => 'Draft',
                'type' => 'car_listing',
                'description' => 'Car listing is in draft status',
                'color' => '#6B7280',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'active',
                'display_name' => 'Active',
                'type' => 'car_listing',
                'description' => 'Car listing is active and visible',
                'color' => '#10B981',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'sold',
                'display_name' => 'Sold',
                'type' => 'car_listing',
                'description' => 'Car has been sold',
                'color' => '#8B5CF6',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'expired',
                'display_name' => 'Expired',
                'type' => 'car_listing',
                'description' => 'Car listing has expired',
                'color' => '#F59E0B',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'suspended',
                'display_name' => 'Suspended',
                'type' => 'car_listing',
                'description' => 'Car listing has been suspended',
                'color' => '#EF4444',
                'sort_order' => 5,
                'is_active' => true,
            ],

            // User Statuses
            [
                'name' => 'active',
                'display_name' => 'Active',
                'type' => 'user',
                'description' => 'User account is active',
                'color' => '#10B981',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'inactive',
                'display_name' => 'Inactive',
                'type' => 'user',
                'description' => 'User account is inactive',
                'color' => '#6B7280',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'suspended',
                'display_name' => 'Suspended',
                'type' => 'user',
                'description' => 'User account has been suspended',
                'color' => '#EF4444',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'pending_verification',
                'display_name' => 'Pending Verification',
                'type' => 'user',
                'description' => 'User account is pending verification',
                'color' => '#F59E0B',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['name' => $status['name'], 'type' => $status['type']],
                $status
            );
        }
    }
}