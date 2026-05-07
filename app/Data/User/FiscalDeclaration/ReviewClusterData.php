<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cluster de risque détecté par le moteur (Phase 11 D2, ADR-0015 § 4).
 *
 * Identifié fonctionnellement par son `fingerprint` déterministe : à
 * la régénération d'une déclaration, les clusters dont le fingerprint
 * est inchangé conservent leur décision (D3). En D2, `decision` et
 * `justification` sont toujours `null` ; le matching avec les
 * décisions persistées arrive en D3.
 */
#[TypeScript]
final class ReviewClusterData extends Data
{
    /**
     * @param  list<ClusterContractData>  $contracts
     */
    public function __construct(
        public RiskCode $code,
        public RiskLevel $level,
        public string $fingerprint,
        #[DataCollectionOf(ClusterContractData::class)]
        public array $contracts,
        public int $contractsCount,
        public int $cumulativeDaysInYear,
        public ?ReviewDecisionType $decision = null,
        public ?string $justification = null,
    ) {}
}
