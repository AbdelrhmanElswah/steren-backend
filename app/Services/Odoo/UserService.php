<?php

namespace App\Services\Odoo;

use App\Models\User;

class UserService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new User());
    }
}
