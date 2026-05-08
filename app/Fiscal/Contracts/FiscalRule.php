<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Enums\Fiscal\TaxType;
use Carbon\CarbonImmutable;

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
 *
 * **Granularité temporelle (chantier κ)** : chaque règle déclare sa
 * période d'applicabilité via `applicabilityStart()` / `applicabilityEnd()`.
 * La grande majorité des règles couvrent l'année fiscale entière et
 * peuvent adopter {@see App\Fiscal\Contracts\Concerns\AnnualRuleTrait}.
 * Les règles partielles (apparition ou disparition en cours d'année)
 * implémentent ces méthodes directement.
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

    /**
     * Date à partir de laquelle la règle est applicable (incluse). Pour
     * une règle annuelle, c'est `{year}-01-01 00:00:00`.
     */
    public function applicabilityStart(): CarbonImmutable;

    /**
     * Date jusqu'à laquelle la règle est applicable (incluse). Pour une
     * règle annuelle, c'est `{year}-12-31 23:59:59`. `null` signifie
     * « open-ended » : règle valide depuis `applicabilityStart()`
     * jusqu'à indéfini.
     */
    public function applicabilityEnd(): ?CarbonImmutable;
}
