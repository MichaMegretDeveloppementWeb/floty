<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification;

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
 * R-2026-005 - CO₂ scale selection (WLTP → NEDC → PA cascade).
 *
 * Strict reproduction of 2025 mechanism: CIBS L. 421-119 (chapeau) and
 * L. 421-119-1 (method cascade) are stable; version 01/01/2022 for
 * L. 421-119, version 31/12/2023 for L. 421-119-1 (LF 2024 art. 97).
 * No 2026 modification.
 *
 * Cascade:
 * - WLTP if WLTP homologation + `co2_wltp` set
 * - else NEDC if NEDC homologation + `co2_nedc` set
 * - else PA (R-2026-006 fallback)
 *
 * The result (`HomologationMethod`) is attached to the context for CO₂
 * `PricingRule`s (R-2026-010 WLTP, R-2026-011 NEDC, R-2026-012 PA)
 * which only execute if they match the resolved method.
 *
 * Legal basis:
 * - CIBS L. 421-119: stable since 01/01/2022.
 * - CIBS L. 421-119-1: stable since 31/12/2023 (LF 2024 art. 97).
 *
 * The scales themselves (L. 421-120 WLTP, L. 421-121 NEDC, L. 421-122
 * PA) are cited on their dedicated rules R-2026-010/011/012 and bear
 * parameters hardened at 01/01/2026 by LF 2024 art. 97-20°.
 */
final readonly class R2026_005_Co2MethodSelection implements ClassificationRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-005';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Sélection du barème CO₂';
    }

    public function description(): string
    {
        return 'Détermine le barème applicable (WLTP / NEDC / PA) à partir des caractéristiques véhicule. Conditions d\'éligibilité textuelles inchangées en 2026 (cascade L. 421-119-1 stable depuis 31/12/2023). Les barèmes appliqués (R-2026-010/011/012) sont durcis au 01/01/2026 par LF 2024 art. 97-20°.';
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
                'article' => 'L. 421-119',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602987/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2026-01-01',
                'consulted_at' => '2026-05-15',
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
            body: "L'aiguillage suit cet arbre : (1) véhicule immatriculé en France à partir du 01/03/2020 et CO₂ WLTP connu → barème WLTP. (2) Véhicule immatriculé entre le 01/06/2004 et 29/02/2020 avec CO₂ NEDC connu → barème NEDC. (3) Dans tous les autres cas (ancien véhicule, données manquantes) → barème Puissance Administrative (CV). Les seuils chiffrés des trois barèmes sont DURCIS au 01/01/2026 par LF 2024 art. 97-20° (programmé en avance par LF 2024, pas par LF 2026) ; l'aiguillage entre les trois reste inchangé.",
            example: 'Peugeot 308 immat. 15/06/2022 avec CO₂ WLTP 100 g/km → WLTP 2026 (213 €). Peugeot 207 immat. 2010 avec NEDC 100 g/km → NEDC 2026 (324 €). Renault 21 immat. 2002, 10 CV → PA 2026 (33 000 €).',
        );
    }
}
