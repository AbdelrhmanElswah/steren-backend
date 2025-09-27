<?php

namespace App\Services\Odoo;

use App\Models\City;

class CityService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new City());
    }
}
