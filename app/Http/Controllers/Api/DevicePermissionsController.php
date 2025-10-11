<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDevicePermissionsRequest;
use App\Models\UserDevicePermission;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DevicePermissionsController extends Controller
{
    use ApiResponse;

    /**
     * Get user's device permissions
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get or create device permissions for user
        $permissions = $user->devicePermissions ?? UserDevicePermission::create([
            'user_id' => $user->id,
            'location' => false,
            'general_notifications' => false,
            'camera' => false,
            'photos' => false,
        ]);

        return $this->success([
            'permissions' => [
                'location' => [
                    'enabled' => $permissions->location,
                    'status' => $permissions->getPermissionStatusText('location'),
                ],
                'general_notifications' => [
                    'enabled' => $permissions->general_notifications,
                    'status' => $permissions->getPermissionStatusText('general_notifications'),
                ],
                'camera' => [
                    'enabled' => $permissions->camera,
                    'status' => $permissions->getPermissionStatusText('camera'),
                ],
                'photos' => [
                    'enabled' => $permissions->photos,
                    'status' => $permissions->getPermissionStatusText('photos'),
                ],
            ],
            'summary' => [
                'total_permissions' => 4,
                'enabled_count' => $permissions->getEnabledPermissionsCount(),
                'has_any_enabled' => $permissions->hasAnyPermissionsEnabled(),
                'last_updated' => $permissions->last_updated_at,
            ]
        ], 'Device permissions retrieved successfully');
    }

    /**
     * Update specific device permission
     */
    public function updatePermission(Request $request, string $permission)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        
        // Get or create device permissions for user
        $permissions = $user->devicePermissions ?? UserDevicePermission::create([
            'user_id' => $user->id,
            'location' => false,
            'general_notifications' => false,
            'camera' => false,
            'photos' => false,
        ]);

        // Validate permission name
        if (!in_array($permission, ['location', 'general_notifications', 'camera', 'photos'])) {
            return $this->error('Invalid permission type', 400);
        }

        // Update the permission
        $success = $permissions->updatePermission($permission, $request->enabled);

        if (!$success) {
            return $this->error('Failed to update permission', 500);
        }

        // Refresh the model to get updated data
        $permissions->refresh();

        return $this->success([
            'permission' => $permission,
            'enabled' => $request->enabled,
            'status' => $permissions->getPermissionStatusText($permission),
            'updated_at' => $permissions->last_updated_at,
        ], 'Permission updated successfully');
    }

    /**
     * Update multiple device permissions
     */
    public function updateMultiple(UpdateDevicePermissionsRequest $request)
    {
        $user = $request->user();
        
        // Get or create device permissions for user
        $permissions = $user->devicePermissions ?? UserDevicePermission::create([
            'user_id' => $user->id,
            'location' => false,
            'general_notifications' => false,
            'camera' => false,
            'photos' => false,
        ]);

        $validatedData = $request->validated();
        
        // Update permissions
        $permissions->update([
            'location' => $validatedData['location'],
            'general_notifications' => $validatedData['general_notifications'],
            'camera' => $validatedData['camera'],
            'photos' => $validatedData['photos'],
            'last_updated_at' => now(),
        ]);

        return $this->success([
            'permissions' => [
                'location' => [
                    'enabled' => $permissions->location,
                    'status' => $permissions->getPermissionStatusText('location'),
                ],
                'general_notifications' => [
                    'enabled' => $permissions->general_notifications,
                    'status' => $permissions->getPermissionStatusText('general_notifications'),
                ],
                'camera' => [
                    'enabled' => $permissions->camera,
                    'status' => $permissions->getPermissionStatusText('camera'),
                ],
                'photos' => [
                    'enabled' => $permissions->photos,
                    'status' => $permissions->getPermissionStatusText('photos'),
                ],
            ],
            'summary' => [
                'total_permissions' => 4,
                'enabled_count' => $permissions->getEnabledPermissionsCount(),
                'has_any_enabled' => $permissions->hasAnyPermissionsEnabled(),
                'last_updated' => $permissions->last_updated_at,
            ]
        ], 'Device permissions updated successfully');
    }

    /**
     * Reset all permissions to default (all disabled)
     */
    public function reset(Request $request)
    {
        $user = $request->user();
        
        $permissions = $user->devicePermissions;
        
        if (!$permissions) {
            return $this->success([
                'message' => 'No permissions to reset',
                'permissions' => [
                    'location' => ['enabled' => false, 'status' => 'OFF'],
                    'general_notifications' => ['enabled' => false, 'status' => 'OFF'],
                    'camera' => ['enabled' => false, 'status' => 'OFF'],
                    'photos' => ['enabled' => false, 'status' => 'OFF'],
                ]
            ], 'Permissions already at default state');
        }

        $permissions->update([
            'location' => false,
            'general_notifications' => false,
            'camera' => false,
            'photos' => false,
            'last_updated_at' => now(),
        ]);

        return $this->success([
            'permissions' => [
                'location' => ['enabled' => false, 'status' => 'OFF'],
                'general_notifications' => ['enabled' => false, 'status' => 'OFF'],
                'camera' => ['enabled' => false, 'status' => 'OFF'],
                'photos' => ['enabled' => false, 'status' => 'OFF'],
            ],
            'summary' => [
                'total_permissions' => 4,
                'enabled_count' => 0,
                'has_any_enabled' => false,
                'last_updated' => $permissions->last_updated_at,
            ]
        ], 'All permissions reset to default');
    }

    /**
     * Get permission statistics for admin
     */
    public function statistics(Request $request)
    {
        // This would typically be for admin users only
        $stats = [
            'total_users' => UserDevicePermission::count(),
            'permissions_breakdown' => [
                'location' => UserDevicePermission::where('location', true)->count(),
                'general_notifications' => UserDevicePermission::where('general_notifications', true)->count(),
                'camera' => UserDevicePermission::where('camera', true)->count(),
                'photos' => UserDevicePermission::where('photos', true)->count(),
            ],
            'users_with_permissions' => UserDevicePermission::where(function($query) {
                $query->where('location', true)
                      ->orWhere('general_notifications', true)
                      ->orWhere('camera', true)
                      ->orWhere('photos', true);
            })->count(),
        ];

        return $this->success($stats, 'Permission statistics retrieved successfully');
    }
}
