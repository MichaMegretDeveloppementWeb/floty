<?php

declare(strict_types=1);

namespace App\Data\User\FiscalReviewDecision;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Distinct;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de la décision humaine sur un cluster de risque (Phase 11
 * D3, ADR-0015 § 6.2). Reçu par `StoreReviewDecisionAction` pour
 * upsert dans `fiscal_review_decisions`.
 *
 * Validation Spatie côté DTO. Validation conditionnelle de la
 * justification obligatoire si `decision = Conserved` ET niveau élevé
 * → portée par l'Action (l'enum `RiskCode::level()` n'est pas évaluable
 * dans une règle Spatie statique).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreReviewDecisionData extends Data
{
    /**
     * @param  list<int>|null  $excludedContractIds
     */
    public function __construct(
        #[Min(1)]
        public int $companyId,
        #[Min(2020), Max(2099)]
        public int $fiscalYear,
        public RiskCode $riskCode,
        #[Size(64)]
        public string $clusterFingerprint,
        public ReviewDecisionType $decision,
        #[Nullable, Max(2000)]
        public ?string $justification = null,
        /**
         * Phase 13 D5.10.S · contractIds explicitement exclus du
         * cluster · ils sont traités comme LCD individuels exemptés
         * R-2024-021 et ne participent pas à l'opt-out si la décision
         * globale est Requalified.
         *
         * Lot 5 D4 · validation typage `ArrayType + Distinct` aligné
         * sur le pattern `StoreContractData::driverIds`. La garantie
         * sémantique d'appartenance des IDs au couple `(company_id,
         * fiscal_year)` est portée par
         * {@see App\Actions\FiscalDeclaration\StoreReviewDecisionAction::guardExcludedContractsBelongToScope()}
         * (validation métier dans l'Action) · ferme le risque IDOR
         * latent V2 multi-tenant.
         */
        #[Sometimes, Nullable, ArrayType, Distinct]
        public ?array $excludedContractIds = null,
    ) {}
}
