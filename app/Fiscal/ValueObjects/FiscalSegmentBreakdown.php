<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Pipeline\PipelineResult;

/**
 * Couple {segment VFC, résultat pipeline partiel} émis par
 * {@see App\Fiscal\Pipeline\VfcSegmentedFiscalExecutor::executeWithSegments()}.
 *
 * Permet aux consommateurs (typiquement
 * {@see App\Services\Fiscal\FleetFiscalAggregator::vehicleFullYearTaxBreakdown()})
 * d'exposer un détail tarifaire par segment VFC dans l'UI : tarif
 * annuel, dûs prorata, exonérations propres au segment. Sans cette
 * exposition, on ne peut pas afficher un calcul cohérent en multi-VFC
 * (chaque segment a sa propre méthode CO₂, ses tarifs, ses
 * exonérations).
 */
final readonly class FiscalSegmentBreakdown
{
    public function __construct(
        public VfcEffectiveSegment $segment,
        public PipelineResult $result,
    ) {}
}
