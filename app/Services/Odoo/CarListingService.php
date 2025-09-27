<?php

namespace App\Services\Odoo;

use App\Models\CarListing;

class CarListingService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarListing());
    }
}
