<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Fiscal\TaxType;

/**
 * Périmètre d'une exonération fiscale : touche-t-elle les deux taxes
 * ou une seule ?
 */
enum ExemptionScope
{
    case Both;
    case Co2Only;
    case PollutantsOnly;

    /**
     * Vrai si le scope couvre la {@see TaxType} donnée.
     */
    public function covers(TaxType $tax): bool
    {
        return match ($this) {
            self::Both => true,
            self::Co2Only => $tax === TaxType::Co2,
            self::PollutantsOnly => $tax === TaxType::Pollutants,
        };
    }
}
