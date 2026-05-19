<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Vehicle usage type (registration field J.3). French administrative codes preserved.
 */
enum VehicleUserType: string
{
    case PassengerCar = 'VP';
    case CommercialVehicle = 'VU';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::PassengerCar => 'VP · Voiture particulière',
            self::CommercialVehicle => 'VU · Véhicule utilitaire',
        };
    }
}
