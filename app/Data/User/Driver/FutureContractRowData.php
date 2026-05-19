<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Contract\ContractType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the "Future contracts to resolve" table on the driver leave modal.
 *
 * `currentDrivers` lists every driver currently on the contract (including
 * the leaving driver). `candidates` lists active company drivers on the
 * exact contract period, excluding every driver already on the contract.
 */
#[TypeScript]
final class FutureContractRowData extends Data
{
    /**
     * @param  list<DriverOptionData>  $currentDrivers
     * @param  list<DriverOptionData>  $candidates
     */
    public function __construct(
        public int $contractId,
        public string $vehicleLicensePlate,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ContractType $contractType,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $currentDrivers,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $candidates,
    ) {}
}
