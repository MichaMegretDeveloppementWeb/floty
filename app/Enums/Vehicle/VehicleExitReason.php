<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Reason for definitive fleet exit (`vehicles.exit_reason`), set iff `exit_date IS NOT NULL`.
 *
 * Distinct from {@see App\Models\VehicleEvent} which represents temporary
 * unavailability (vehicle remains in fleet). See ADR-0018.
 */
enum VehicleExitReason: string
{
    case Sold = 'sold';
    case Destroyed = 'destroyed';
    case Transferred = 'transferred';
    case StolenUnrecovered = 'stolen_unrecovered';
    case Other = 'other';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Vendu',
            self::Destroyed => 'Détruit',
            self::Transferred => 'Transféré',
            self::StolenUnrecovered => 'Vol non résolu',
            self::Other => 'Autre',
        };
    }
}
