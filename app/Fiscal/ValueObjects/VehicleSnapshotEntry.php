<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Ligne « véhicule » d'un {@see FiscalDeclarationSnapshot} : taxe due
 * par ce véhicule au sein du couple `(company, year)` du snapshot
 * parent (Phase 11 D5.2).
 *
 * Les valeurs `co2Due`, `pollutantsDue`, `totalDue` sont arrondies au
 * centime (HALF_UP) ; leur somme sur l'ensemble des entrées est égale
 * au {@see FiscalDeclarationSnapshot::$totalDue} (R-2024-003 invariant
 * d'arrondi unique par redevable).
 */
final readonly class VehicleSnapshotEntry
{
    public function __construct(
        public int $vehicleId,
        public string $vehicleLabel,
        public int $daysAssigned,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
    ) {}
}
