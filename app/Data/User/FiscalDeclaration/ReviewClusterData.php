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
 * Cluster de risque détecté par le moteur (Phase 11 D2, ADR-0015 § 4,
 * refondu D5.10.N · critère plage couverte au lieu du cumul somme).
 *
 * Identifié fonctionnellement par son `fingerprint` déterministe : à
 * la régénération d'une déclaration, les clusters dont le fingerprint
 * est inchangé conservent leur décision (D3). En D2, `decision` et
 * `justification` sont toujours `null` ; le matching avec les
 * décisions persistées arrive en D3.
 *
 * **Sémantique D5.10.N · plage couverte** · le critère qui qualifie une
 * chaîne LCD à risque n'est plus la somme des `expandToDaysInYear` (qui
 * surcompte en cas de chevauchements et de chaînes multi-véhicules),
 * mais la **plage temporelle continue** allant de la date de début la
 * plus précoce à la date de fin la plus tardive du cluster, bornée à
 * l'année fiscale stricte. Cela reflète la doctrine R-LCD-CHAIN
 * (CIBS L. 421-141) · l'élément à risque est la continuité temporelle
 * d'usage, pas la somme des durées contractuelles individuelles.
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
        /** Phase 13 D5.10.N · plage couverte en jours = `(coverageEndDate - coverageStartDate) + 1`, bornée à l'année fiscale. */
        public int $coveragePeriodDays,
        /** Phase 13 D5.10.N · ISO Y-m-d, date de début effective bornée à l'année. */
        public string $coverageStartDate,
        /** Phase 13 D5.10.N · ISO Y-m-d, date de fin effective bornée à l'année. */
        public string $coverageEndDate,
        /** Phase 13 D5.10.N · nombre de véhicules distincts touchés par la chaîne. */
        public int $distinctVehiclesCount,
        public ?ReviewDecisionType $decision = null,
        public ?string $justification = null,
        /**
         * Phase 13 D5.10.S · contractIds explicitement exclus de la
         * chaîne par l'utilisateur. Vide par défaut · ces contrats
         * sont traités comme LCD individuels exemptés R-2024-021 et
         * ne participent pas à l'opt-out en cas de décision
         * Requalified. Les stats `contractsCount`, `coveragePeriodDays`,
         * `coverageStartDate`/`coverageEndDate`, `distinctVehiclesCount`
         * reflètent les contrats **inclus** (= contracts - exclusions).
         * La liste `contracts` reste brute (= tous les contrats
         * détectés du cluster) pour permettre à la modale d'afficher
         * et toggler chaque contrat.
         *
         * @var list<int>
         */
        public array $excludedContractIds = [],
    ) {}
}
