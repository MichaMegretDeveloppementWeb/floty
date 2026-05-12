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
 * Vue liste d'un contrat - utilisée par la table de la page
 * `User/Contracts/Index/Index.vue` (chantier 04.G). Champs essentiels
 * uniquement pour limiter le payload Inertia.
 */
#[TypeScript]
final class ContractListItemData extends Data
{
    /**
     * @param  list<DriverOptionData>  $drivers  Conducteurs désignés sur ce contrat (pivot `contract_drivers`, 0 à N).
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLicensePlate,
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
        public ContractType $contractType,
        public ?string $contractReference,
        /**
         * Phase 13 D5.10.L · taxe fiscale due par ce contrat (somme CO₂ +
         * polluants), arrondie en cents puis convertie en euros. Calcul
         * effectué côté backend lors du chargement de la liste.
         */
        public float $totalTax = 0.0,
        /**
         * Phase 13 D5.10.L · prix location du contrat (cents → euros).
         * Null si tarif annuel manquant pour le véhicule.
         */
        public ?float $rentalPrice = null,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $start = $contract->start_date;
        $end = $contract->end_date;

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
            vehicleIsExited: $contract->vehicle->is_exited,
            companyId: $contract->company_id,
            companyShortCode: $contract->company->short_code,
            companyLegalName: $contract->company->legal_name,
            companyColor: $contract->company->color,
            drivers: $drivers,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            durationDays: $duration,
            contractType: $contract->contract_type,
            contractReference: $contract->contract_reference,
        );
    }
}
