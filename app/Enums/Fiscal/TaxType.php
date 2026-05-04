<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

/**
 * Les deux taxes annuelles sur véhicules couvertes par Floty V1
 * (ex-TVS depuis 2022 - cf. CIBS art. L. 421-119 et suivants).
 *
 * | Valeur      | CIBS             | Libellé officiel                                     |
 * |-------------|------------------|------------------------------------------------------|
 * | Co2         | art. L. 421-120  | Taxe annuelle sur les émissions de dioxyde de carbone|
 * | Pollutants  | art. L. 421-125  | Taxe annuelle sur les émissions de polluants atmo.   |
 */
enum TaxType: string
{
    case Co2 = 'co2';
    case Pollutants = 'pollutants';
}
