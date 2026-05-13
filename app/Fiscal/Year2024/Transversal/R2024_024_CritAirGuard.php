<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-024 · Garde-fou Crit'Air.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle décrit un garde-fou UI non bloquant qui contrôle la
 * cohérence entre la catégorie polluants CIBS calculée (E / 1 / les
 * plus polluants, L. 421-134) et la vignette Crit'Air attendue. La
 * vignette Crit'Air relève du Code de la route (R. 318-2) et de
 * l'arrêté du 21 juin 2016, indépendamment de la fiscalité.
 *
 * **Implémentation effective** · le garde-fou est codé côté frontend
 * dans `resources/js/Composables/Vehicle/useCritAirCheck.ts`. Cette
 * classe porte uniquement les métadonnées pour la page « Règles de
 * calcul » et le seeder de l'index `fiscal_rules`.
 */
final readonly class R2024_024_CritAirGuard implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-024';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return "Garde-fou Crit'Air";
    }

    public function description(): string
    {
        return "Contrôle de cohérence entre la catégorie polluants CIBS calculée (E / 1 / les plus polluants, L. 421-134) et la vignette Crit'Air attendue pour le véhicule. La vignette Crit'Air relève du Code de la route (R. 318-2) et de l'arrêté du 21 juin 2016, indépendamment de la fiscalité. Le garde-fou émet une alerte non bloquante en cas d'incohérence afin que l'utilisateur vérifie la saisie.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 24;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-134',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Pollutants];
    }

    /**
     * Override · l'implémentation effective du garde-fou Crit'Air vit
     * dans le composable Vue `useCritAirCheck.ts`, pas dans cette classe
     * PHP. Cette méthode informe le seeder pour que la page « Règles de
     * calcul » expose le bon chemin à l'utilisateur curieux.
     */
    public function codeReference(): string
    {
        return 'resources/js/Composables/Vehicle/useCritAirCheck.ts';
    }
}
