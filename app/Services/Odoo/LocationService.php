<?php

namespace App\Services\Odoo;

use App\Models\Location;

class LocationService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new Location());
    }
}
