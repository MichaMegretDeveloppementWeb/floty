<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification;

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
 * R-2025-005 · Sélection du barème CO₂ + R-2025-006 bascule PA si donnée
 * CO₂ manquante. Reconduction stricte R-2024-005 (conditions d'éligibilité
 * inchangées en 2025 ; modifications LF 2025 art. 28 portent sur d'autres
 * articles).
 *
 * Cascade · WLTP si homologation WLTP + `co2_wltp` renseigné → NEDC si
 * homologation NEDC + `co2_nedc` renseigné → PA sinon (fallback
 * R-2025-006). Le résultat (`HomologationMethod`) est attaché au contexte
 * pour les `PricingRule` CO₂ qui ne s'exécutent que si elles correspondent
 * à la méthode résolue.
 *
 * Base légale enrichie · L. 421-119 (chapeau), L. 421-119-1 (date de
 * référence), L. 421-120 (barème WLTP), L. 421-121 (barème NEDC),
 * L. 421-122 (barème PA), L. 421-6 (définition WLTP).
 */
final readonly class R2025_005_Co2MethodSelection implements ClassificationRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-005';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Sélection du barème CO₂';
    }

    public function description(): string
    {
        return 'Détermine le barème applicable (WLTP / NEDC / PA) à partir des caractéristiques véhicule. Conditions d\'éligibilité textuelles inchangées en 2025 (LF 2025 art. 28 ne modifie pas ces articles).';
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
            // Cascade de sélection du barème CO₂ · seuls les articles
            // chapeau (L. 421-119) et de date de référence (L. 421-119-1)
            // sont cités ici. Les barèmes eux-mêmes (L. 421-120 WLTP,
            // L. 421-121 NEDC, L. 421-122 PA) sont cités sur leurs
            // règles dédiées (R-2025-010/011/012).
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602987/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-119-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048802414/2025-01-01',
                'consulted_at' => '2026-05-14',
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
            tab: RuleTab::Calcul,
            section: RuleSection::Aiguillage,
            title: 'Étape 2 : quel barème CO₂ appliquer ?',
            pitch: 'WLTP, NEDC ou Puissance Administrative, choix automatique selon la date de première immatriculation et les données disponibles.',
            body: "L'aiguillage suit cet arbre : (1) véhicule immatriculé en France à partir du 01/03/2020 et CO₂ WLTP connu → barème WLTP. (2) Véhicule immatriculé entre le 01/06/2004 et 29/02/2020 avec CO₂ NEDC connu → barème NEDC. (3) Dans tous les autres cas (ancien véhicule, données manquantes) → barème Puissance Administrative (CV). Les seuils chiffrés des trois barèmes ont été DURCIS au 01/01/2025 par la LF 2024 art. 97 ; l'aiguillage entre les trois reste inchangé.",
            example: 'Peugeot 308 immat. 15/06/2022 avec CO₂ WLTP 100 g/km → WLTP 2025 (193 €). Peugeot 207 immat. 2010 avec NEDC 100 g/km → NEDC 2025 (284 €). Renault 21 immat. 2002, 10 CV → PA 2025 (29 750 €).',
        );
    }
}
