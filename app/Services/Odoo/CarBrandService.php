<?php

namespace App\Services\Odoo;

use App\Models\CarBrand;

class CarBrandService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarBrand());
    }
}
