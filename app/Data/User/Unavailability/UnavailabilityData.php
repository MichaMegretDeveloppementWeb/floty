<?php

declare(strict_types=1);

namespace App\Data\User\Unavailability;

use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Unavailability;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation d'une indisponibilité pour affichage (page Show
 * véhicule, listes etc.).
 *
 *   - daysCount : nombre de jours couverts (inclusif), ou 0 si
 *                 l'indispo est encore en cours (end_date null) — le
 *                 front affiche alors « depuis le {start_date} ».
 */
#[TypeScript]
final class UnavailabilityData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleId,
        public UnavailabilityType $type,
        public bool $hasFiscalImpact,
        public string $startDate,
        public ?string $endDate,
        public ?string $description,
        public int $daysCount,
    ) {}

    public static function fromModel(Unavailability $u): self
    {
        $daysCount = $u->end_date === null
            ? 0
            : ((int) $u->start_date->diffInDays($u->end_date)) + 1;

        return new self(
            id: $u->id,
            vehicleId: $u->vehicle_id,
            type: $u->type,
            hasFiscalImpact: $u->has_fiscal_impact,
            startDate: $u->start_date->toDateString(),
            endDate: $u->end_date?->toDateString(),
            description: $u->description,
            daysCount: $daysCount,
        );
    }
}
