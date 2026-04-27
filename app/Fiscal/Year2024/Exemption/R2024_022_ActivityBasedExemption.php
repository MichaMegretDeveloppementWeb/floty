<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-022 — Exonérations à activité (CIBS L. 421-131 / L. 421-143).
 *
 * Couvre transport public de personnes, activité agricole/forestière,
 * enseignement de la conduite, compétitions sportives.
 *
 * Conditions cumulatives V1 (simplification, cf. plan 1.9 D1) :
 *   1. Entreprise déclare une activité exonérée
 *      (`companies.exempted_activity !== 'none'`)
 *   2. Véhicule **affecté à 100 %** à cette activité
 *      (`vehicle_fiscal_characteristics.affected_to_exempted_activity_percent === 100`)
 *
 * Le **prorata partiel** (entreprise mixte 60 % agricole + 40 %
 * commercial) est repoussé à V2 — V1 traite l'exonération comme
 * binaire : tout ou rien sur la base du véhicule.
 *
 * **Inactif par défaut V1**.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple, cette règle retourne `notExempt()`.
 */
final readonly class R2024_022_ActivityBasedExemption implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2024-022';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return ExemptionVerdict::notExempt();
        }

        if ($fiscal->affected_to_exempted_activity_percent !== 100) {
            return ExemptionVerdict::notExempt();
        }

        // Vérification de l'activité exonérée côté entreprise — non
        // disponible dans le contexte V1.
        return ExemptionVerdict::notExempt();
    }
}
