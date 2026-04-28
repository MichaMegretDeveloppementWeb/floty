<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Statut courant d'un véhicule (colonne `vehicles.current_status`).
 *
 * Invariants (validés en applicatif, cf. 01-schema-metier.md § 2) :
 *   - Si `vehicles.exit_date IS NULL` → statut ∈ { Active, Maintenance }
 *   - Si `vehicles.exit_date IS NOT NULL` → statut ∈ { Sold, Destroyed, Other }
 */
enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Sold = 'sold';
    case Destroyed = 'destroyed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Maintenance => 'Maintenance',
            self::Sold => 'Vendu',
            self::Destroyed => 'Détruit',
            self::Other => 'Autre',
        };
    }
}
