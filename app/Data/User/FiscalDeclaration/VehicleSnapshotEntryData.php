<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Fiscal\ValueObjects\VehicleSnapshotEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation frontend d'une ligne « véhicule » d'un snapshot fiscal
 * (Phase 11 D5.6). Miroir DTO du VO domaine
 * {@see VehicleSnapshotEntry}.
 *
 * Les montants `co2Due, pollutantsDue, totalDue` sont déjà arrondis au
 * centime (HALF_UP). Affichés dans le tableau `vehicleBreakdown` du
 * composant {@see resources/js/Components/Domain/Declaration/FiscalSummaryCard.vue}.
 */
#[TypeScript]
final class VehicleSnapshotEntryData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $vehicleLabel,
        public int $daysAssigned,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
    ) {}

    public static function fromValueObject(VehicleSnapshotEntry $vo): self
    {
        return new self(
            vehicleId: $vo->vehicleId,
            vehicleLabel: $vo->vehicleLabel,
            daysAssigned: $vo->daysAssigned,
            co2Due: $vo->co2Due,
            pollutantsDue: $vo->pollutantsDue,
            totalDue: $vo->totalDue,
        );
    }
}
