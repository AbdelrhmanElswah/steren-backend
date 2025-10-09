<?php

namespace App\Services\Odoo;

use App\Models\Budget;

class BudgetService extends OdooCrudService
{
    public function __construct()
    {
        parent::__construct(new Budget());
    }
}

