<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2024-005 - Sélection du barème CO₂ + R-2024-006 - bascule PA si
 * donnée CO₂ manquante.
 *
 * Règle :
 *   - Si la méthode d'homologation du véhicule est WLTP et qu'on a un
 *     `co2_wltp` renseigné → barème WLTP.
 *   - Sinon, si NEDC et qu'on a un `co2_nedc` renseigné → barème NEDC.
 *   - Sinon → barème Puissance Administrative (R-2024-006 fallback).
 *
 * Le résultat (`HomologationMethod`) est attaché au contexte pour les
 * `PricingRule` CO₂ qui ne s'exécutent que si elles correspondent à la
 * méthode résolue.
 */
final readonly class R2024_005_Co2MethodSelection implements ClassificationRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-005';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Sélection du barème CO₂';
    }

    public function description(): string
    {
        return 'Détermine le barème applicable (WLTP / NEDC / PA) à partir des caractéristiques véhicule.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 5;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            ['type' => 'CIBS', 'article' => 'L. 421-119-1'],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        $method = $this->resolveMethod($fiscal);

        return $context
            ->withResolvedCo2Method($method)
            ->withAppliedRule($this->ruleCode());
    }

    private function resolveMethod(VehicleFiscalCharacteristics $fiscal): HomologationMethod
    {
        if ($fiscal->homologation_method === HomologationMethod::Wltp && $fiscal->co2_wltp !== null) {
            return HomologationMethod::Wltp;
        }
        if ($fiscal->homologation_method === HomologationMethod::Nedc && $fiscal->co2_nedc !== null) {
            return HomologationMethod::Nedc;
        }

        return HomologationMethod::Pa;
    }
}
