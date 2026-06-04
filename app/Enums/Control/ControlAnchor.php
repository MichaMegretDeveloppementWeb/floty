<?php

declare(strict_types=1);

namespace App\Enums\Control;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Anchor date from which a regulatory control's first due date is computed
 * (Chantier B). Each case maps to a non-nullable `vehicles.*` date column via
 * {@see self::vehicleColumn()}; the échéance engine (B2) adds the initial
 * duration to that anchor, then the cycle to each subsequent execution.
 *
 * `FirstOriginRegistration` is the default: the contrôle technique legal anchor
 * is the vehicle's first registration date (to be confirmed on primary source
 * before the échéance calculation is frozen in B2).
 */
#[TypeScript]
enum ControlAnchor: string
{
    case FirstOriginRegistration = 'first_origin_registration';
    case FirstFrenchRegistration = 'first_french_registration';
    case Acquisition = 'acquisition';
    case EconomicUse = 'economic_use';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::FirstOriginRegistration => '1re mise en circulation (origine)',
            self::FirstFrenchRegistration => '1re immatriculation française',
            self::Acquisition => "Date d'acquisition",
            self::EconomicUse => 'Date de mise en service économique',
        };
    }

    /**
     * Name of the (non-nullable) `vehicles` date column this anchor reads from.
     */
    public function vehicleColumn(): string
    {
        return match ($this) {
            self::FirstOriginRegistration => 'first_origin_registration_date',
            self::FirstFrenchRegistration => 'first_french_registration_date',
            self::Acquisition => 'acquisition_date',
            self::EconomicUse => 'first_economic_use_date',
        };
    }
}
