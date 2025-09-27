<?php

namespace App\Services\Odoo;

use App\Models\CarInspection;

class CarInspectionService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarInspection());
    }
}
