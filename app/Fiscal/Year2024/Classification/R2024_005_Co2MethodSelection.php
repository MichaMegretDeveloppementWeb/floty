<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
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
    use RuleActiveByDefaultTrait;

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
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::Aiguillage,
            title: 'Étape 2 : quel barème CO₂ appliquer ?',
            pitch: 'WLTP, NEDC ou Puissance Administrative, choix automatique selon la date de première immatriculation et les données disponibles.',
            body: "L'aiguillage suit cet arbre : (1) véhicule immatriculé en France à partir du 01/03/2020 et CO₂ WLTP connu → barème WLTP. (2) Véhicule immatriculé entre le 01/06/2004 et 29/02/2020 avec CO₂ NEDC connu → barème NEDC. (3) Dans tous les autres cas (ancien véhicule, données manquantes) → barème Puissance Administrative (CV).",
            example: 'Peugeot 308 immat. 15/06/2022 avec CO₂ WLTP 100 g/km → WLTP. Peugeot 207 immat. 2010 avec NEDC 130 g/km → NEDC. Renault 21 immat. 2002, 7 CV → PA.',
        );
    }
}
