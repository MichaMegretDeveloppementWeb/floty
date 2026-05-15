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
 * R-2026-014 · Tarif annuel forfaitaire polluants (CIBS L. 421-135) ·
 * **version 01/01 → 28/02/2026** (avant LF 2026 art. 58 V, IV).
 *
 * **Règle pipeline (Pricing)** · ADR-0022 · première scission **matérielle**
 * 2026 (les 2 autres · R-2026-013/013-bis catégorisation et
 * R-2026-018/018-bis OIG · sont rédactionnelles). L'article L. 421-135
 * a été revalorisé par la LOI n° 2026-103 du 19/02/2026 art. 58 (V),
 * IV à effet du 01/03/2026. R-2026-014 couvre la période **avant**.
 * La période **après** est portée par {@see R2026_014bis_PollutantsFlat}.
 *
 * **Tarifs identiques à 2024-2025** ·
 *   - E (électrique / hydrogène)        →   0 €
 *   - Catégorie 1 (essence/gaz Euro 5/6)→ 100 €
 *   - Plus polluants                    → 500 €
 *
 * Mécanique de tarification partagée via
 * {@see PollutantsFlatLogicTrait} avec
 * {@see R2026_014bis_PollutantsFlat}.
 *
 * **Source légale** · audit Chrome live 15/05/2026 ·
 * - CIBS L. 421-135 v 31/12/2023 → 01/03/2026 (texte stable depuis 2022).
 */
final readonly class R2026_014_PollutantsFlat implements PricingRule
{
    use PollutantsFlatLogicTrait;
    use RuleActiveByDefaultTrait;

    private PollutantTariff $tariff;

    public function __construct()
    {
        $this->tariff = new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 100.0,
            PollutantCategory::MostPolluting->value => 500.0,
        ]);
    }

    protected function tariffTable(): PollutantTariff
    {
        return $this->tariff;
    }

    public function ruleCode(): string
    {
        return 'R-2026-014';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 2, 28, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Tarif forfaitaire polluants (version 01/01 → 28/02/2026)';
    }

    public function description(): string
    {
        return "Tarif annuel forfaitaire par catégorie d'émissions (E = 0 € / 1 = 100 € / plus polluants = 500 €). Version 01/01-28/02/2026 · tarifs reconduits depuis 2024 (L. 421-135 v 31/12/2023 → 01/03/2026). À partir du 01/03/2026, les tarifs sont revalorisés de +30 % par LF 2026 art. 58 (V), IV (R-2026-014-bis).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844528/2026-01-01',
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
            title: 'Barème polluants v 01/01 → 28/02/2026 (tarifs reconduits 2024-2025)',
            pitch: 'Tarif annuel forfaitaire selon la catégorie polluants du véhicule. Période 01/01-28/02/2026 · tarifs identiques à 2024-2025.',
            body: "Le tarif annuel plein ne dépend ni de l'émission réelle ni de la cylindrée · c'est un forfait par catégorie. Il est multiplié par le prorata jours utilisés / 365. **Pour la période 01/03-31/12/2026, les tarifs sont revalorisés de +30 %** par LF 2026 art. 58 (V), IV (cf. R-2026-014-bis).",
            flatBrackets: new FlatBracketsTable(
                header: ['Catégorie polluants', 'Tarif annuel 01/01-28/02/2026'],
                rows: [
                    new FlatBracketRow(
                        category: 'E · électrique / hydrogène',
                        amount: '0 €',
                        note: 'Effet du barème, pas une exonération.',
                    ),
                    new FlatBracketRow(category: '1 · essence/gaz Euro 5 ou 6', amount: '100 €'),
                    new FlatBracketRow(
                        category: 'Véhicules les plus polluants',
                        amount: '500 €',
                        note: 'Inclut tous les Diesel.',
                    ),
                ],
            ),
            example: 'Peugeot 308 essence Euro 6 (catégorie 1), utilisée du 01/01 au 28/02/2026 (59 jours) : 100 × 59/365 = 16,16 €. La même utilisation à partir du 01/03 sera tarifée à 130 €/an (R-2026-014-bis).',
        );
    }
}
