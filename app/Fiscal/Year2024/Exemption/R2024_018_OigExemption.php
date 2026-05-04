<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-018 - Exonération organisme d'intérêt général (CIBS L. 421-126
 * / L. 421-138).
 *
 * Si l'entreprise utilisatrice est un organisme d'intérêt général
 * (CGI art. 261, 7°) ET que le véhicule est exclusivement affecté à son
 * activité non lucrative, l'exonération s'applique sur les deux taxes.
 * Flag : `companies.is_oig`.
 *
 * **Inactif par défaut V1** : aucune entreprise utilisatrice Floty
 * actuelle n'est OIG. La règle est structurellement câblée pour
 * activation future via seeder/UI.
 *
 * Note V1 : tant que le {@see PipelineContext} ne porte pas la
 * `Company` du couple, cette règle retourne `notExempt()`. Le critère
 * d'affectation exclusive sera évalué côté contrat (V2) - pas sur la
 * VFC, qui ne porte pas la sémantique d'usage par entreprise.
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
        // Tant que le contexte ne porte pas la company, pas
        // d'évaluation possible. Cas attendu V1.
        return ExemptionVerdict::notExempt();
    }
}
