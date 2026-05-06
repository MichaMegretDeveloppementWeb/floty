<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\User\Driver\DriverOptionData;
use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'un contrat - utilisée par la page de détail
 * (chantier 04.G : `User/Contracts/Show/Index.vue`).
 *
 * Cf. ADR-0014 D6 (page détail + documents joints) et `taxes-rules/2024.md`
 * v2.0 R-2024-021 pour la mécanique LCD par contrat individuel.
 */
#[TypeScript]
final class ContractData extends Data
{
    /**
     * @param  list<DriverOptionData>  $drivers  Conducteurs désignés sur ce contrat (0, 1 ou plusieurs ; pivot pur égalitaire `contract_drivers`)
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLicensePlate,
        public string $vehicleBrand,
        public string $vehicleModel,
        public bool $vehicleIsExited,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $drivers,
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

        $drivers = $contract->drivers
            ->map(fn ($d): DriverOptionData => new DriverOptionData(
                id: $d->id,
                fullName: $d->full_name,
                initials: $d->initials,
            ))
            ->values()
            ->all();

        return new self(
            id: $contract->id,
            vehicleId: $contract->vehicle_id,
            vehicleLicensePlate: $contract->vehicle->license_plate,
            vehicleBrand: $contract->vehicle->brand,
            vehicleModel: $contract->vehicle->model,
            vehicleIsExited: $contract->vehicle->is_exited,
            companyId: $contract->company_id,
            companyShortCode: $contract->company->short_code,
            companyLegalName: $contract->company->legal_name,
            companyColor: $contract->company->color,
            drivers: $drivers,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            durationDays: $duration,
            contractReference: $contract->contract_reference,
            contractType: $contract->contract_type,
            notes: $contract->notes,
        );
    }
}
