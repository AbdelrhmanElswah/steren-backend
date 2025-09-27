<?php

namespace App\Services\Odoo;

use App\Models\CarModel;

class CarModelService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarModel());
    }
}
