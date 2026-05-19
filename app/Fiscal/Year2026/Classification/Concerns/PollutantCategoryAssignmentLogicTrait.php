<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification\Concerns;

use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * Shared 2026 pollutant categorisation logic for R-2026-013 and
 * R-2026-013-bis.
 *
 * CIBS L. 421-134 has two versions in 2026 (modified on 01/09/2026 by
 * Ordonnance n° 2025-1247 art. 7 and art. 49), which mandates strict
 * ADR-0022 (two distinct Floty fiscal rules: R-2026-013 for
 * 01/01-31/08 and R-2026-013-bis for 01/09-31/12). The Ordo 2025-1247
 * modifications to L. 421-134 are purely editorial (removal of the
 * "dans sa rédaction en vigueur" insert). The E / Cat1 / MostPolluting
 * categorisation cascade is strictly identical between the two
 * versions. The logic is factored here to avoid duplication and
 * guarantee synchronisation between the two versions.
 *
 * Delegation to PollutantCategory::derive(): the full cascade lives in
 * the {@see PollutantCategory::derive()} enum so the same logic
 * applies to the Repository (VFC write), the fiscal pipeline (this
 * rule), and the frontend (TypeScript mirror).
 *
 * If a future version of L. 421-134 introduces a material change to
 * the E / Cat1 / MostPolluting scope, this trait will be split into
 * two variants (each class consuming its own version).
 */
trait PollutantCategoryAssignmentLogicTrait
{
    abstract public function ruleCode(): string;

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        $category = PollutantCategory::derive(
            $fiscal->energy_source,
            $fiscal->euro_standard,
            $fiscal->underlying_combustion_engine_type,
        );

        return $context
            ->withResolvedPollutantCategory($category)
            ->withAppliedRule($this->ruleCode());
    }
}
