<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Fiscal\TaxType;

/**
 * Scope of an exemption: does it cover both taxes or just one?
 */
enum ExemptionScope
{
    case Both;
    case Co2Only;
    case PollutantsOnly;

    /**
     * True if this scope covers the given {@see TaxType}.
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
