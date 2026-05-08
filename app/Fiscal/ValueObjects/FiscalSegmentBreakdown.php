<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Pipeline\PipelineResult;
use Carbon\CarbonImmutable;

/**
 * Détail d'une exécution de pipeline pour un sous-segment du produit
 * cartésien {VFC × Règles} (chantier κ.4 - granularité temporelle).
 *
 * Émis par
 * {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor::executeWithSegments()}.
 *
 * Sémantique des champs :
 * - `start` / `end` : bornes inclusives de l'**intersection** entre le
 *   segment VFC et le segment règles. C'est sur cette fenêtre que le
 *   pipeline a été exécuté.
 * - `vfcSegment` : VFC active pendant cet intervalle (utile pour
 *   exposer les caractéristiques du véhicule à l'UI).
 * - `ruleSegment` : règles applicables pendant cet intervalle (utile
 *   pour l'audit et l'exposition « quelles règles ont produit ce
 *   montant »).
 * - `result` : `PipelineResult` partiel (raw co2 / raw pollutants
 *   prorata sur cette fenêtre). Le total annuel se reconstruit en
 *   sommant les `co2DueRaw` + `pollutantsDueRaw` puis en arrondissant
 *   une seule fois (cf. R-2024-003).
 *
 * Permet aux consommateurs (typiquement
 * {@see App\Services\Fiscal\FleetFiscalAggregator::vehicleFullYearTaxBreakdown()})
 * d'exposer un détail tarifaire par sous-segment dans l'UI : tarif
 * annuel, dûs prorata, exonérations propres au segment.
 */
final readonly class FiscalSegmentBreakdown
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public VfcEffectiveSegment $vfcSegment,
        public RuleEffectiveSegment $ruleSegment,
        public PipelineResult $result,
    ) {}
}
