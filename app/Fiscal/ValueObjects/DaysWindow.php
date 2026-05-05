<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Fenêtre temporelle [start, end] (bornes inclusives) utilisée pour
 * restreindre le compte des jours présents dans le pipeline fiscal.
 *
 * Posée par {@see App\Fiscal\Pipeline\VfcSegmentedFiscalExecutor} sur
 * le {@see App\Fiscal\Pipeline\PipelineContext} quand un calcul est
 * segmenté par VFC : chaque sous-calcul reçoit la fenêtre du segment
 * VFC actif, mais voit toujours les contrats entiers (pour que les
 * règles per-contract comme R-2024-021 LCD jugent sur la durée totale
 * du contrat, pas sur la portion clippée).
 *
 * R-2024-002 (DailyProrata) intersecte les jours présents
 * (`expandToDaysInYear`) avec cette fenêtre si elle est posée.
 */
final readonly class DaysWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Vrai ssi la date est dans la fenêtre (bornes incluses), comparée
     * à la granularité du jour (heure/minute ignorées).
     */
    public function contains(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return ! $day->lessThan($this->start->startOfDay())
            && ! $day->greaterThan($this->end->startOfDay());
    }
}
