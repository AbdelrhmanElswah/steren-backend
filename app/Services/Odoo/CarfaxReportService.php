<?php

namespace App\Services\Odoo;

use App\Models\CarfaxReport;

class CarfaxReportService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarfaxReport());
    }
}
