<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Fiscal\Contracts\FiscalRule;
use Carbon\CarbonImmutable;

/**
 * Segment temporel d'une année fiscale sur lequel l'ensemble des règles
 * applicables est stable (chantier κ - granularité temporelle).
 *
 * Émis par
 * {@see App\Fiscal\Pipeline\RuleEffectiveSegmenter::segmentsForYear()}.
 * Bornes inclusives, clippées à l'année consultée :
 *   - `start` >= year-01-01
 *   - `end`   <= year-12-31
 *
 * Un segment couvre toujours au moins 1 jour. La liste `rules` est
 * toujours non-vide (les segments sans règle applicable ne sont pas
 * matérialisés). L'ordre des règles dans la liste est celui du registry
 * pour l'année consultée (préserve l'ordre d'exécution du pipeline).
 *
 * Consommé par {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor}
 * (chantier κ.4) qui combine ce segment avec les segments VFC pour
 * exécuter le pipeline sur le produit cartésien VFC × Règles.
 *
 * Analogue temporel de {@see VfcEffectiveSegment} (segment VFC).
 */
final readonly class RuleEffectiveSegment
{
    /**
     * @param  non-empty-list<FiscalRule>  $rules
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $rules,
    ) {}
}
