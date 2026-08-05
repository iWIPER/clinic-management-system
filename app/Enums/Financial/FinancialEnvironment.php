<?php

namespace App\Enums\Financial;

enum FinancialEnvironment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';
}