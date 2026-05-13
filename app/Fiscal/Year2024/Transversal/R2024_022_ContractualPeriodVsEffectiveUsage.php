<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-022 · Période contractuelle vs usage effectif.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle énonce le principe que le numérateur du prorata
 * journalier compte les jours de la période d'affectation contractuelle
 * et non les jours d'usage effectif du véhicule. La règle pipeline
 * R-2024-002 (DailyProrata) en découle, et seules les indispos
 * réductrices R-2024-008 viennent diminuer ce numérateur.
 */
final readonly class R2024_022_ContractualPeriodVsEffectiveUsage implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-022';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Période contractuelle vs usage effectif';
    }

    public function description(): string
    {
        return "Le numérateur du prorata journalier compte le nombre de jours de la période d'affectation contractuelle (date de début → date de fin de contrat), pas le nombre de jours pendant lesquels le véhicule a effectivement circulé ou été utilisé. Un contrat de 30 jours pendant lesquels le véhicule n'a roulé qu'une journée compte 30 jours au numérateur. La doctrine BOFiP § 170 le précise : « le nombre de jours pendant lesquels le véhicule a effectivement circulé n'est donc pas pris en compte ». Seules les indispos réductrices (R-2024-008) viennent diminuer ce numérateur.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 22;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-107',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 170',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#170_4136',
                'consulted_at' => '2026-05-06',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }
}
