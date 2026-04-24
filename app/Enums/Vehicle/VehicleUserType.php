<?php

namespace App\Enums\Vehicle;

/**
 * Type d'utilisation du véhicule (rubrique J.3 de la carte grise).
 * Codes administratifs français préservés (cf. E1).
 *
 * - VP : Voiture Particulière
 * - VU : Véhicule Utilitaire
 */
enum VehicleUserType: string
{
    case PassengerCar = 'VP';
    case CommercialVehicle = 'VU';

    public function label(): string
    {
        return match ($this) {
            self::PassengerCar => 'VP — Voiture particulière',
            self::CommercialVehicle => 'VU — Véhicule utilitaire',
        };
    }
}
