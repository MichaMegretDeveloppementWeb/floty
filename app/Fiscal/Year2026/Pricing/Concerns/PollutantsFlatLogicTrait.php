<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Pricing\Concerns;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\PollutantTariff;

/**
 * Logique partagée de tarification polluants 2026 (R-2026-014 et
 * R-2026-014-bis).
 *
 * **Pourquoi un trait** · l'article CIBS L. 421-135 a deux versions
 * matériellement différentes en 2026 (revalorisation +30 % au 01/03/2026
 * par LF 2026 art. 58 (V), IV), ce qui impose ADR-0022 strict · deux
 * règles fiscales Floty distinctes (R-2026-014 pour 01/01-28/02 et
 * R-2026-014-bis pour 01/03-31/12). Les **tarifs diffèrent** entre les
 * 2 versions (Cat1 100 → 130 €, MostPolluting 500 → 650 €) mais la
 * **mécanique d'application** est strictement identique · lecture de
 * la catégorie polluants déterminée par R-2026-013/013-bis, lookup
 * dans la table de tarifs propre à chaque version, affectation au
 * contexte pipeline. On factorise cette mécanique ici pour éviter la
 * duplication.
 *
 * Chaque classe consommatrice fournit sa propre instance de
 * {@see PollutantTariff} via la méthode abstraite `tariffTable()`.
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
