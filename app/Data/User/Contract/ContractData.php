<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'un contrat — utilisée par la page de détail
 * (chantier 04.G : `User/Contracts/Show/Index.vue`).
 *
 * Cf. ADR-0014 D6 (page détail + documents joints) et `taxes-rules/2024.md`
 * v2.0 R-2024-021 pour la mécanique LCD par contrat individuel.
 */
#[TypeScript]
final class ContractData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleId,
        public int $companyId,
        public ?int $driverId,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ?string $contractReference,
        public ContractType $contractType,
        public ?string $notes,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $start = $contract->start_date;
        $end = $contract->end_date;

        // Durée inclusive : (end - start) en jours + 1
        $duration = (int) $start->diffInDays($end) + 1;

        return new self(
            id: $contract->id,
            vehicleId: $contract->vehicle_id,
            companyId: $contract->company_id,
            driverId: $contract->driver_id,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            durationDays: $duration,
            contractReference: $contract->contract_reference,
            contractType: $contract->contract_type,
            notes: $contract->notes,
        );
    }
}
