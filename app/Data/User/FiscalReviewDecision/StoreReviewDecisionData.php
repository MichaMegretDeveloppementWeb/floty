<?php

declare(strict_types=1);

namespace App\Data\User\FiscalReviewDecision;

use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
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
 * Human decision payload for a risk cluster (ADR-0015 § 6.2). Consumed
 * by `StoreReviewDecisionAction` for upsert in `fiscal_review_decisions`.
 *
 * The "justification required when `Conserved` + high level" rule is
 * enforced by the Action since `RiskCode::level()` is not evaluable in
 * a static Spatie validation rule.
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
         * Contract ids explicitly opted out of the cluster; treated as
         * individual LCD exempted under R-2024-021 and excluded from any
         * Requalified opt-out. Belonging to `(company_id, fiscal_year)`
         * is enforced by
         * {@see StoreReviewDecisionAction::guardExcludedContractsBelongToScope()}
         * to close a latent multi-tenant IDOR.
         */
        #[Sometimes, Nullable, ArrayType, Distinct]
        public ?array $excludedContractIds = null,
    ) {}
}
