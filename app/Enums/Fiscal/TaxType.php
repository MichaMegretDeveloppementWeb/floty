<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

/**
 * The two annual vehicle taxes covered by Floty V1 (ex-TVS, CIBS L. 421-119 et seq.):
 * - `Co2` (CIBS L. 421-120): annual CO₂ emissions tax.
 * - `Pollutants` (CIBS L. 421-125): annual atmospheric pollutants tax.
 */
enum TaxType: string
{
    case Co2 = 'co2';
    case Pollutants = 'pollutants';
}
