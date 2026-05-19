<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Homologation method used to select the applicable CO₂ scale (CIBS L. 421-120/121/122).
 * - `WLTP`: mandatory for France first-registration on or after 2020-03-01.
 * - `NEDC`: legacy method.
 * - `PA`: administrative power, fallback when CO₂ is missing or vehicle is pre-2004.
 */
enum HomologationMethod: string
{
    case Wltp = 'WLTP';
    case Nedc = 'NEDC';
    case Pa = 'PA';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Wltp => 'WLTP (immat. ≥ 2020)',
            self::Nedc => 'NEDC (immat. 2004–2020)',
            self::Pa => 'Puissance administrative (sans CO₂)',
        };
    }
}
