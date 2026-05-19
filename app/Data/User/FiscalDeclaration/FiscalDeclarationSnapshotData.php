<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Frontend mirror of a declaration's fiscal snapshot, computed on demand
 * by the controller via `DeclarationFiscalEngine`. Powers the Show and
 * Review pages (summary card + contract list with cluster grouping).
 *
 * The totals `co2DueTotal`, `pollutantsDueTotal`, `totalDue` are already
 * rounded to the cent (R-2024-003 invariant).
 */
#[TypeScript]
final class FiscalDeclarationSnapshotData extends Data
{
    /**
     * @param  list<ContractSnapshotEntryData>  $contractBreakdown
     * @param  list<AppliedDecisionEntryData>  $appliedDecisions
     * @param  list<int>  $optOutContractIds
     */
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        /** ISO 8601 (Y-m-d\TH:i:sP). */
        public string $computedAt,
        public float $co2DueTotal,
        public float $pollutantsDueTotal,
        public float $totalDue,
        #[DataCollectionOf(ContractSnapshotEntryData::class)]
        public array $contractBreakdown,
        #[DataCollectionOf(AppliedDecisionEntryData::class)]
        public array $appliedDecisions,
        public array $optOutContractIds,
        public ?string $companyAddress = null,
    ) {}

    public static function fromValueObject(FiscalDeclarationSnapshot $vo): self
    {
        return new self(
            companyId: $vo->companyId,
            companyShortCode: $vo->companyShortCode,
            companyLegalName: $vo->companyLegalName,
            fiscalYear: $vo->fiscalYear,
            computedAt: $vo->computedAt->toIso8601String(),
            co2DueTotal: $vo->co2DueTotal,
            pollutantsDueTotal: $vo->pollutantsDueTotal,
            totalDue: $vo->totalDue,
            contractBreakdown: array_map(
                static fn ($entry): ContractSnapshotEntryData => ContractSnapshotEntryData::fromValueObject($entry),
                $vo->contractBreakdown,
            ),
            appliedDecisions: array_map(
                static fn ($entry): AppliedDecisionEntryData => AppliedDecisionEntryData::fromValueObject($entry),
                $vo->appliedDecisions,
            ),
            optOutContractIds: $vo->optOutContractIds,
            companyAddress: $vo->companyAddress,
        );
    }
}
