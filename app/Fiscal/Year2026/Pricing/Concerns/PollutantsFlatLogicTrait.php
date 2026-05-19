<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Pricing\Concerns;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\PollutantTariff;

/**
 * Shared 2026 pollutants pricing logic for R-2026-014 and
 * R-2026-014-bis.
 *
 * CIBS L. 421-135 has two materially different versions in 2026
 * (+30% revaluation at 01/03/2026 by LF 2026 art. 58 (V), IV), which
 * mandates strict ADR-0022 (two distinct Floty fiscal rules:
 * R-2026-014 for 01/01-28/02 and R-2026-014-bis for 01/03-31/12). The
 * tariffs differ between the two versions (Cat1 100 → 130 €,
 * MostPolluting 500 → 650 €) but the application mechanism is
 * strictly identical: read the pollutant category determined by
 * R-2026-013/013-bis, look up in the tariff table specific to each
 * version, attach to the pipeline context. The mechanism is factored
 * here to avoid duplication.
 *
 * Each consuming class provides its own {@see PollutantTariff}
 * instance via the abstract `tariffTable()` method.
 */
trait PollutantsFlatLogicTrait
{
    abstract public function ruleCode(): string;

    abstract protected function tariffTable(): PollutantTariff;

    public function price(PipelineContext $context): PipelineContext
    {
        $category = $context->resolvedPollutantCategory;
        if ($category === null) {
            return $context;
        }

        return $context
            ->withPollutantsFullYearTariff($this->tariffTable()->tariffFor($category))
            ->withAppliedRule($this->ruleCode());
    }
}
