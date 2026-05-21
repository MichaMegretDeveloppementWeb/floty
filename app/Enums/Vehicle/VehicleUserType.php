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
     * Derived from the EU reception category (registration field J.1).
     * M1 → VP, N1 → VU. The DB enforces this 1:1 mapping via the
     * `chk_vfc_user_type_consistent_with_reception` CHECK constraint,
     * so always derive on write to avoid partial-update violations.
     */
    public static function fromReceptionCategory(ReceptionCategory $category): self
    {
        return match ($category) {
            ReceptionCategory::M1 => self::PassengerCar,
            ReceptionCategory::N1 => self::CommercialVehicle,
        };
    }

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
