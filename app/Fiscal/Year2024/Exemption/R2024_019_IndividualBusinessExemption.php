<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-019 — Exonération entreprise individuelle (CIBS L. 421-127
 * / L. 421-139).
 *
 * Si l'entreprise utilisatrice est une personne physique exerçant en
 * son nom propre (entrepreneur individuel BIC/BNC), l'exonération
 * s'applique sur les deux taxes. Flag : `companies.is_individual_business`.
 *
 * **Inactif par défaut V1** : aucune entreprise utilisatrice Floty
 * actuelle n'est en nom propre (toutes sont des sociétés). La règle
 * est structurellement câblée pour activation future via seeder/UI.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple, cette règle retourne `notExempt()`.
 */
final readonly class R2024_019_IndividualBusinessExemption implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2024-019';
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
        // Tant que le contexte ne porte pas la company, pas
        // d'évaluation possible. Cas attendu V1.
        return ExemptionVerdict::notExempt();
    }
}
