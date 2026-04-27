<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-018 — Exonération organisme d'intérêt général (CIBS L. 421-126
 * / L. 421-138).
 *
 * Conditions cumulatives :
 *   1. Entreprise utilisatrice = OIG (CGI art. 261, 7°) — flag
 *      `companies.is_oig`
 *   2. Véhicule **exclusivement** affecté à l'activité non lucrative
 *      — `vehicle_fiscal_characteristics.affected_to_exempted_activity_percent === 100`
 *
 * **Inactif par défaut V1** : aucune entreprise utilisatrice du
 * périmètre Floty actuel n'est OIG. La règle est structurellement
 * câblée — il suffira de poser `is_oig = true` côté seeder/UI quand
 * une entreprise éligible entrera dans la flotte.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple (cas des consommateurs `FiscalCalculator` façade
 * et `FleetFiscalAggregator` actuels), cette règle retourne
 * **toujours** `notExempt()` — comportement attendu, car aucune
 * entreprise n'a `is_oig = true` aujourd'hui.
 */
final readonly class R2024_018_OigExemption implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2024-018';
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
        // Tant que le contexte ne porte pas la company du couple,
        // pas d'évaluation possible. Cas attendu V1 (cf. docblock).
        // Sera implémenté quand un consommateur fournira la company.

        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return ExemptionVerdict::notExempt();
        }

        if ($fiscal->affected_to_exempted_activity_percent !== 100) {
            return ExemptionVerdict::notExempt();
        }

        // Vérification du statut OIG côté entreprise — non disponible
        // dans le contexte V1, donc no-op.
        return ExemptionVerdict::notExempt();
    }
}
