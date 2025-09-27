<?php

namespace App\Services\Odoo;

use App\Models\CarSubmission;

class CarSubmissionService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new CarSubmission());
    }
}
