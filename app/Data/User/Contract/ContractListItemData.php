<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue liste d'un contrat — utilisée par la table de la page
 * `User/Contracts/Index/Index.vue` (chantier 04.G). Champs essentiels
 * uniquement pour limiter le payload Inertia.
 */
#[TypeScript]
final class ContractListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLicensePlate,
        public int $companyId,
        public string $companyShortCode,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ContractType $contractType,
        public ?string $contractReference,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $start = $contract->start_date;
        $end = $contract->end_date;

        $duration = (int) $start->diffInDays($end) + 1;

        return new self(
            id: $contract->id,
            vehicleId: $contract->vehicle_id,
            vehicleLicensePlate: $contract->vehicle->license_plate,
            companyId: $contract->company_id,
            companyShortCode: $contract->company->short_code,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            durationDays: $duration,
            contractType: $contract->contract_type,
            contractReference: $contract->contract_reference,
        );
    }
}
