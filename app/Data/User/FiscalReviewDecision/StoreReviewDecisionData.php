<?php

declare(strict_types=1);

namespace App\Data\User\FiscalReviewDecision;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Size;
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
    ) {}
}
