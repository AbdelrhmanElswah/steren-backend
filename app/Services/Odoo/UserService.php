<?php

namespace App\Services\Odoo;

use App\Models\User;

class UserService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new User());
    }

    /**
     * Update user by Odoo ID with field mapping
     * Maps Odoo field names to User model field names:
     * - birth_date -> birthday
     * - profile_img -> profile_picture
     */
    public function updateByOdooId(string $odooId, array $data): User
    {
        // Map Odoo field names to User model field names
        $mappedData = [];
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'birth_date':
                    // Map birth_date to birthday
                    $mappedData['birthday'] = $value;
                    break;
                case 'profile_img':
                    // Map profile_img to profile_picture
                    // Allow empty string to clear profile picture
                    $mappedData['profile_picture'] = $value ?: null;
                    break;
                default:
                    // Keep other fields as-is
                    $mappedData[$key] = $value;
                    break;
            }
        }

        return parent::updateByOdooId($odooId, $mappedData);
    }
}
