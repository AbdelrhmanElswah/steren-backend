<?php

namespace App\Services\Odoo;

use App\Models\Studio;

class StudioService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new Studio());
    }
}
