<?php

declare(strict_types=1);

namespace App\Enums\Contract;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Rental contract business qualification (ADR-0014).
 *
 * - `lcd`: short-term rental (`is_short_term_rental` algorithm, R-2024-021 v2.0).
 *   The stored value is indicative; fiscal exoneration is derived from dates.
 * - `lld`: long-term rental (out of LCD scope).
 * - `mise_a_disposition_assimilee`: other forms of provision (CIBS L. 421-99, BOFiP § 130-150).
 */
#[TypeScript]
enum ContractType: string
{
    case Lcd = 'lcd';
    case Lld = 'lld';
    case MiseADispositionAssimilee = 'mise_a_disposition_assimilee';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lcd => 'Location de courte durée (LCD)',
            self::Lld => 'Location de longue durée (LLD)',
            self::MiseADispositionAssimilee => 'Mise à disposition assimilée',
        };
    }
}
