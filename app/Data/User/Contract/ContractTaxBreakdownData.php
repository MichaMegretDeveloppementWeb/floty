<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full fiscal breakdown of a contract.
 *
 * A contract spanning two civil years carries two `years` entries.
 * `totalDue` is the sum of each year's `totalDue` (each already rounded
 * half-up to 2 decimals).
 */
#[TypeScript]
final class ContractTaxBreakdownData extends Data
{
    /**
     * @param  list<ContractTaxYearBreakdownData>  $years
     */
    public function __construct(
        #[DataCollectionOf(ContractTaxYearBreakdownData::class)]
        public array $years,
        public float $totalDue,
    ) {}
}
