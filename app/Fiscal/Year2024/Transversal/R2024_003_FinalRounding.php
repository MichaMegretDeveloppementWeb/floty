<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * R-2024-003 - Arrondi half-up commercial (CIBS L. 131-1).
 *
 * **Sémantique BOFiP** : « le montant total à payer par chaque
 * redevable est arrondi à l'euro le plus proche, sans arrondi
 * intermédiaire ». L'arrondi par redevable s'opère donc dans
 * {@see FleetFiscalAggregator::companyAnnualTax()},
 * qui somme les `co2DueRaw` + `pollutantsDueRaw` de tous les véhicules
 * d'une entreprise et arrondit **une seule fois**.
 *
 * Cette classe règle est conservée comme **marqueur** dans le pipeline
 * (apparaît dans `appliedRuleCodes` du snapshot, page « Règles de
 * calcul »). Elle ne modifie pas les montants - l'arrondi par couple
 * (utile pour l'affichage par ligne du PDF / drawer planning) est
 * appliqué par le pipeline lui-même dans `buildResult()`.
 */
final readonly class R2024_003_FinalRounding implements TransversalRule
{
    public function ruleCode(): string
    {
        return 'R-2024-003';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function apply(PipelineContext $context): PipelineContext
    {
        return $context->withAppliedRule($this->ruleCode());
    }
}
