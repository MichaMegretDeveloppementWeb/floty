<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification\Concerns;

use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * Logique partagée de catégorisation polluants 2026 (R-2026-013 et
 * R-2026-013-bis).
 *
 * **Pourquoi un trait** · l'article CIBS L. 421-134 a deux versions en
 * 2026 (modifié au 01/09/2026 par Ordonnance n° 2025-1247 art. 7 et
 * art. 49), ce qui impose ADR-0022 strict · deux règles fiscales Floty
 * distinctes (R-2026-013 pour 01/01-31/08 et R-2026-013-bis pour
 * 01/09-31/12). Or les modifications Ordo 2025-1247 sur L. 421-134 sont
 * **purement rédactionnelles** (suppression de l'incise « dans sa
 * rédaction en vigueur »). La cascade de catégorisation E / Cat1 /
 * MostPolluting reste **strictement identique** entre les deux versions.
 * On factorise la logique ici pour éviter la duplication et garantir
 * la synchronisation des deux versions.
 *
 * **Délégation à PollutantCategory::derive()** · la cascade complète
 * vit dans l'enum {@see PollutantCategory::derive()} pour que la même
 * logique s'applique au Repository (écriture VFC), au pipeline fiscal
 * (cette règle) et au front (mirror TypeScript).
 *
 * **Si une version future de L. 421-134 introduit un changement matériel
 * du périmètre E / Cat1 / MostPolluting**, ce trait sera scindé en deux
 * variantes (chaque classe consommera sa version propre).
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
