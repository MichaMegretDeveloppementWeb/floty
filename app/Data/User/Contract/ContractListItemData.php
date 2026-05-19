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
 * Contract row used by the listing table.
 *
 * `totalTax` and `rentalPrice` are nullable to support the slim listing
 * path: when the caller cannot pay for the fiscal pipeline up front (the
 * index defers costs via `Inertia::defer`), they instantiate the DTO
 * without these fields. Pages that resolve costs server-side
 * (Show / Company tab via `listForCompany`) pass them.
 */
#[TypeScript]
final class ContractListItemData extends Data
{
    /**
     * @param  list<DriverOptionData>  $drivers  Drivers attached via the `contract_drivers` pivot (0..N).
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
         * Fiscal tax due by this contract (CO2 + pollutants, half-up
         * rounded then converted to euros). Nullable on the deferred
         * listing path: the row is rendered with a skeleton until the
         * second request (`ContractQueryService::costsForContractIds`)
         * fills it in.
         */
        public ?float $totalTax = null,
        /**
         * Rental price (cents to euros). Null when the vehicle has no
         * yearly tariff defined OR when not yet resolved (deferred path).
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
