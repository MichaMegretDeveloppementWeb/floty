<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation frontend du snapshot fiscal d'une déclaration
 * (Phase 11 D5.6, refondu D5.8 avec breakdown par contrat). Miroir
 * DTO du VO domaine {@see FiscalDeclarationSnapshot}.
 *
 * Calculé à la volée par
 * {@see App\Http\Controllers\User\FiscalDeclaration\DeclarationController}
 * via {@see App\Services\Fiscal\Declaration\DeclarationFiscalEngine},
 * passé aux pages Inertia Show et Review pour alimenter
 * {@see resources/js/Components/Domain/Declaration/FiscalSummaryCard.vue}
 * (synthèse) et {@see resources/js/Components/Domain/Declaration/DeclarationContractList.vue}
 * (liste contrats avec groupage cluster automatique).
 *
 * Les montants `co2DueTotal, pollutantsDueTotal, totalDue` sont déjà
 * arrondis au centime (R-2024-003 invariant).
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
        );
    }
}
