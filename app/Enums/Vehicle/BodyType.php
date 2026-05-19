<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Vehicle body type (registration field J.2). French administrative codes preserved.
 */
enum BodyType: string
{
    case InteriorDriving = 'CI';
    case StationWagon = 'BB';
    case LightTruck = 'CTTE';
    case Pickup = 'BE';
    case Handicap = 'HB';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::InteriorDriving => 'Conduite intérieure (berline, monospace)',
            self::StationWagon => 'Break',
            self::LightTruck => 'Camionnette',
            self::Pickup => 'Pick-up',
            self::Handicap => 'Véhicule aménagé handicap',
        };
    }
}
