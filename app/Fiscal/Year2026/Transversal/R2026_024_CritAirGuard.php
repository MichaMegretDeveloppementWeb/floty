<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-024 · Garde-fou Crit'Air.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2025-024.
 * Contrôle de cohérence non bloquant entre la catégorie polluants
 * CIBS calculée (E / 1 / les plus polluants, L. 421-134) et la
 * vignette Crit'Air attendue. La vignette Crit'Air relève du Code de
 * la route (R. 318-2) et de l'arrêté du 21 juin 2016, indépendamment
 * de la fiscalité.
 *
 * **Toilettage Ordo 2025-1247 art. 7** · L. 421-134 a été toiletté
 * rédactionnellement au 01/09/2026 (suppression « dans sa rédaction
 * en vigueur »). La **doctrine du garde-fou ne change pas** · les
 * 3 catégories CIBS (E / 1 / les plus polluants) restent inchangées
 * et la cohérence avec les 6 niveaux Crit'Air reste opérante toute
 * l'année. Une version unique annuelle suffit pour cette règle
 * informative. Les deux versions de L. 421-134 sont citées en
 * legalBasis pour transparence.
 *
 * **Implémentation effective** · le garde-fou est codé côté frontend
 * dans `resources/js/Composables/Vehicle/useCritAirCheck.ts`. Cette
 * classe porte uniquement les métadonnées pour la page « Règles de
 * calcul » et le seeder de l'index `fiscal_rules`.
 */
final readonly class R2026_024_CritAirGuard implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-024';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return "Garde-fou Crit'Air";
    }

    public function description(): string
    {
        return "Contrôle de cohérence entre la catégorie polluants CIBS calculée (E / 1 / les plus polluants, L. 421-134) et la vignette Crit'Air attendue pour le véhicule. La vignette Crit'Air relève du Code de la route (R. 318-2) et de l'arrêté du 21 juin 2016, indépendamment de la fiscalité. Le garde-fou émet une alerte non bloquante en cas d'incohérence afin que l'utilisateur vérifie la saisie. Reconduction stricte 2025 → 2026 · toilettage rédactionnel L. 421-134 au 01/09/2026 (Ordo 2025-1247 art. 7) sans impact sur la doctrine.";
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
                'article' => 'L. 421-134 (v 01/01-31/08/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-134 (v 01/09-31/12/2026 · toilettage Ordo 2025-1247 art. 7)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2026-09-01',
                'consulted_at' => '2026-05-15',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreInterne,
            title: "Garde-fou Crit'Air",
            pitch: "Vérifie que la vignette Crit'Air saisie pour le véhicule est cohérente avec la catégorie polluants CIBS calculée. Alerte non bloquante uniquement.",
            body: "L'application utilise deux classifications distinctes mais liées · la **catégorie polluants CIBS** (3 valeurs · E, 1, « plus polluants ») qui sert au calcul de la taxe annuelle polluants (R-2026-013/013-bis), et la **vignette Crit'Air** (6 niveaux · E, 1, 2, 3, 4, 5) qui relève du Code de la route et de la circulation en zones à faibles émissions. Les deux dépendent toutes deux de la motorisation et de la norme Euro du véhicule, donc elles doivent être cohérentes · un Crit'Air 1 doit donner CIBS catégorie 1, un Crit'Air 5 (diesel ancien) doit donner CIBS « plus polluants », etc. Si la vignette saisie ne correspond pas à la catégorie calculée par l'application, c'est qu'au moins une donnée du véhicule est probablement erronée · L'application affiche une alerte pour inviter l'utilisateur à vérifier sa saisie. Le calcul de taxe utilise quand même la catégorie CIBS calculée (qui est la base légale de la taxe), pas la vignette Crit'Air.",
        );
    }
}
