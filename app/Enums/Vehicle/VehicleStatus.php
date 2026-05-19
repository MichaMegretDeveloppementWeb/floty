<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Current vehicle status (`vehicles.current_status`).
 *
 * Application-level invariants:
 * - `exit_date IS NULL` → status ∈ { Active, Maintenance }
 * - `exit_date IS NOT NULL` → status ∈ { Sold, Destroyed, Other }
 */
enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Sold = 'sold';
    case Destroyed = 'destroyed';
    case Other = 'other';

    /**
     * French label for display.
     */
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
