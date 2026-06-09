<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Driver\DriverOptionData;
use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One rental contract currently active on the vehicle (today is within
 * `[start_date, end_date]`), for the "current status" card on the overview
 * tab. Lean projection: the renting company, the period, the type and the
 * listed drivers, enough to render the card without opening the contract.
 */
#[TypeScript]
final class CurrentRentalData extends Data
{
    /**
     * @param  list<DriverOptionData>  $drivers  Drivers listed on the contract (0..N).
     */
    public function __construct(
        public int $id,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        public ContractType $contractType,
        public string $startDate,
        public string $endDate,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $drivers,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $drivers = $contract->drivers
            ->map(static fn ($d): DriverOptionData => new DriverOptionData(
                id: $d->id,
                fullName: $d->full_name,
                initials: $d->initials,
            ))
            ->values()
            ->all();

        return new self(
            id: $contract->id,
            companyId: $contract->company_id,
            companyShortCode: $contract->company->short_code,
            companyLegalName: $contract->company->legal_name,
            companyColor: $contract->company->color,
            contractType: $contract->contract_type,
            startDate: $contract->start_date->toDateString(),
            endDate: $contract->end_date->toDateString(),
            drivers: $drivers,
        );
    }
}
