<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Enums\Fiscal\TaxType;

/**
 * Interface base de toute règle fiscale Floty (cf. ADR-0006 § 1).
 *
 * Chaque règle réelle implémente l'un des 5 sous-types :
 *   - {@see ClassificationRule} (qualification d'une caractéristique)
 *   - {@see PricingRule}        (tarif annuel plein)
 *   - {@see ExemptionRule}      (court-circuit conditionnel)
 *   - {@see AbatementRule}      (modification d'entrée avant tarif)
 *   - {@see TransversalRule}    (prorata, arrondi, indispos…)
 *
 * `ruleCode()` est l'identifiant publié dans `taxes-rules/{year}.md`
 * (format `R-{year}-{nnn}`). Il est immuable (cf. ADR-0009) et apparaît
 * dans les snapshots PDF + la page « Règles de calcul ».
 */
interface FiscalRule
{
    public function ruleCode(): string;

    /**
     * Taxes concernées : `[Co2]`, `[Pollutants]` ou les deux. Permet
     * au pipeline de filtrer les règles selon la taxe en cours
     * d'évaluation.
     *
     * @return list<TaxType>
     */
    public function taxesConcerned(): array;
}
