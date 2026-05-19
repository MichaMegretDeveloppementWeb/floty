<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * European reception category (registration field J.1).
 * - `M1`: passenger car (≤ 8 seats besides driver).
 * - `N1`: light truck (gross weight ≤ 3.5 t).
 */
enum ReceptionCategory: string
{
    case M1 = 'M1';
    case N1 = 'N1';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::M1 => 'M1 - Voiture particulière (≤ 8 places)',
            self::N1 => 'N1 - Camionnette (PTAC ≤ 3,5 t)',
        };
    }
}
