<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Pricing;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\ValueObjects\FlatBracketRow;
use App\Fiscal\ValueObjects\FlatBracketsTable;
use App\Fiscal\ValueObjects\PollutantTariff;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Fiscal\Year2026\Pricing\Concerns\PollutantsFlatLogicTrait;
use Carbon\CarbonImmutable;

/**
 * R-2026-014-bis - Pollutants flat annual tariff (CIBS L. 421-135),
 * version 01/03 → 31/12/2026 (after LF 2026 art. 58 V, IV).
 *
 * Pipeline rule (Pricing). Counterpart to
 * {@see R2026_014_PollutantsFlat} which covers 01/01-28/02.
 * L. 421-135 revalued by LOI n° 2026-103 du 19/02/2026 art. 58 (V),
 * IV effective 01/03/2026.
 *
 * Material +30% revaluation vs R-2026-014:
 *   - E (electric / hydrogen)            →   0 € (unchanged)
 *   - Category 1 (petrol/gas Euro 5/6)   → 130 € (vs 100 € before, +30%)
 *   - Most polluting                     → 650 € (vs 500 € before, +30%)
 *
 * First revaluation of pollutant tariffs since 2022. This modification
 * has a real impact on the calculation (unlike the editorial splits
 * R-2026-013-bis and R-2026-018-bis).
 *
 * Pricing logic shared through {@see PollutantsFlatLogicTrait}.
 *
 * Cross-period weighting for a full-year 2026 LLD contract: the
 * `FiscalSegmentedExecutor` automatically segments the calculation by
 * applicability period. For a Cat1 LLD vehicle full-year 2026:
 *   (100 × 59 + 130 × 306) / 365 = 125.15 €/year
 *
 * Legal basis:
 * - CIBS L. 421-135 v 01/03/2026 → 01/01/2027.
 * - LOI n° 2026-103 du 19/02/2026 art. 58 (V), IV (effective 01/03/2026).
 */
final readonly class R2026_014bis_PollutantsFlat implements PricingRule
{
    use PollutantsFlatLogicTrait;
    use RuleActiveByDefaultTrait;

    private PollutantTariff $tariff;

    public function __construct()
    {
        // Tariffs revalued +30% by LF 2026 art. 58 (V), IV.
        $this->tariff = new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 130.0,
            PollutantCategory::MostPolluting->value => 650.0,
        ]);
    }

    protected function tariffTable(): PollutantTariff
    {
        return $this->tariff;
    }

    public function ruleCode(): string
    {
        return 'R-2026-014-bis';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Tarif forfaitaire polluants (version 01/03 → 31/12/2026, revalorisé +30 % par LF 2026 art. 58)';
    }

    public function description(): string
    {
        return "Tarif annuel forfaitaire revalorisé +30 % par catégorie d'émissions (E = 0 € / 1 = 130 € / plus polluants = 650 €). Version 01/03-31/12/2026 · première revalorisation des tarifs polluants depuis 2022 par LF 2026 art. 58 (V), IV (LOI n° 2026-103 du 19/02/2026).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        // Must follow R-2026-014 in display order.
        return 14;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-135',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844528/2026-03-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2026 art. 58 V',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000053508155',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Barème polluants v 01/03 → 31/12/2026 (revalorisation +30 % LF 2026)',
            pitch: 'Tarif annuel forfaitaire selon la catégorie polluants. Période 01/03-31/12/2026 · première revalorisation +30 % depuis 2022 par LF 2026 art. 58.',
            body: "Le tarif annuel plein ne dépend ni de l'émission réelle ni de la cylindrée · c'est un forfait par catégorie. Il est multiplié par le prorata jours utilisés / 365. **Pour un contrat LLD couvrant toute l'année 2026**, le `FiscalSegmentedExecutor` segmente le calcul · 59 jours à l'ancien tarif (R-2026-014) + 306 jours au nouveau tarif (R-2026-014-bis). Exemple Cat1 LLD full-year 2026 · (100 × 59 + 130 × 306) / 365 = **125,15 €**.",
            flatBrackets: new FlatBracketsTable(
                header: ['Catégorie polluants', 'Tarif annuel 01/03-31/12/2026'],
                rows: [
                    new FlatBracketRow(
                        category: 'E · électrique / hydrogène',
                        amount: '0 €',
                        note: 'Inchangé vs 2024-2025.',
                    ),
                    new FlatBracketRow(category: '1 · essence/gaz Euro 5 ou 6', amount: '130 €', note: '+30 % vs 100 € précédemment.'),
                    new FlatBracketRow(
                        category: 'Véhicules les plus polluants',
                        amount: '650 €',
                        note: '+30 % vs 500 € précédemment. Inclut tous les Diesel.',
                    ),
                ],
            ),
            example: 'Peugeot 308 essence Euro 6 (catégorie 1), utilisée du 01/03 au 31/12/2026 (306 jours) : 130 × 306/365 = 108,99 €. Pour un contrat LLD full-year, moyenne pondérée = 125,15 €.',
        );
    }
}
