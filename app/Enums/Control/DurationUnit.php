<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Unit of a regulatory control duration (initial validity and recurring cycle).
 * The échéance engine (B2) interprets the paired value with this unit when
 * adding to the anchor or to a previous execution date.
 */
#[TypeScript]
enum DurationUnit: string
{
    case Years = 'years';
    case Months = 'months';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Years => 'ans',
            self::Months => 'mois',
        };
    }
}
