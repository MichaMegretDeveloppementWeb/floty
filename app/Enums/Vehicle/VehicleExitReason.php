<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Motif de sortie de flotte (colonne `vehicles.exit_reason`).
 *
 * Renseigné ssi `vehicles.exit_date IS NOT NULL`
 * (cf. 01-schema-metier.md § 2 — invariant applicatif).
 *
 * Distinction avec {@see App\Enums\Unavailability\UnavailabilityType} :
 * `VehicleExitReason` caractérise une **sortie définitive** de flotte
 * (le véhicule ne reviendra pas), tandis que `UnavailabilityType`
 * caractérise une **indisponibilité temporaire** (le véhicule reste en
 * flotte). Cf. ADR-0018 (cycle de vie véhicule).
 *
 * En particulier :
 *   - `StolenUnrecovered` : vol acté définitivement sans suite (≠
 *     `UnavailabilityType::theft` qui est un vol récent susceptible
 *     d'être résolu).
 *   - `Destroyed` : destruction VHU (C. route R. 322-9), sortie
 *     définitive (≠ ancien `UnavailabilityType::destruction_certificate`
 *     retiré par ADR-0016 rev. 1.1).
 */
enum VehicleExitReason: string
{
    case Sold = 'sold';
    case Destroyed = 'destroyed';
    case Transferred = 'transferred';
    case StolenUnrecovered = 'stolen_unrecovered';
    case Other = 'other';

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
