<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-024 - Crit'Air consistency guard.
 *
 * Documentation-only rule, strict reproduction of R-2024-024.
 * Non-blocking consistency check between the computed CIBS pollutant
 * category (E / 1 / most polluting, L. 421-134) and the expected
 * Crit'Air sticker. The Crit'Air sticker falls under the Code de la
 * route (R. 318-2) and the arrêté du 21 juin 2016, independent of
 * taxation.
 *
 * The actual guard is implemented on the frontend in
 * `resources/js/Composables/Vehicle/useCritAirCheck.ts`. This class
 * only carries metadata for the "Règles de calcul" page and the
 * `fiscal_rules` index seeder.
 */
final readonly class R2025_024_CritAirGuard implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-024';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Garde-fou Crit'Air";
    }

    public function description(): string
    {
        return "Contrôle de cohérence entre la catégorie polluants CIBS calculée (E / 1 / les plus polluants, L. 421-134) et la vignette Crit'Air attendue pour le véhicule. La vignette Crit'Air relève du Code de la route (R. 318-2) et de l'arrêté du 21 juin 2016, indépendamment de la fiscalité. Le garde-fou émet une alerte non bloquante en cas d'incohérence afin que l'utilisateur vérifie la saisie. Reconduction stricte 2024.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2025-01-01',
                'consulted_at' => '2026-05-14',
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
     * The Crit'Air guard implementation lives in the
     * `useCritAirCheck.ts` Vue composable, not in this PHP class.
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
            body: "L'application utilise deux classifications distinctes mais liées · la **catégorie polluants CIBS** (3 valeurs · E, 1, « plus polluants ») qui sert au calcul de la taxe annuelle polluants (R-2025-013), et la **vignette Crit'Air** (6 niveaux · E, 1, 2, 3, 4, 5) qui relève du Code de la route et de la circulation en zones à faibles émissions. Les deux dépendent toutes deux de la motorisation et de la norme Euro du véhicule, donc elles doivent être cohérentes · un Crit'Air 1 doit donner CIBS catégorie 1, un Crit'Air 5 (diesel ancien) doit donner CIBS « plus polluants », etc. Si la vignette saisie ne correspond pas à la catégorie calculée par l'application, c'est qu'au moins une donnée du véhicule est probablement erronée · L'application affiche une alerte pour inviter l'utilisateur à vérifier sa saisie. Le calcul de taxe utilise quand même la catégorie CIBS calculée (qui est la base légale de la taxe), pas la vignette Crit'Air.",
        );
    }
}
