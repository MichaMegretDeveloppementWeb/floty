<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Motif de sortie de flotte (colonne `vehicles.exit_reason`).
 *
 * Renseigné ssi `vehicles.exit_date IS NOT NULL`
 * (cf. 01-schema-metier.md § 2 — invariant applicatif).
 */
enum VehicleExitReason: string
{
    case Sold = 'sold';
    case Destroyed = 'destroyed';
    case Transferred = 'transferred';
    case Other = 'other';
}
