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
 * R-2025-022 · Période contractuelle vs usage effectif.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2024-022.
 * Le numérateur du prorata journalier compte les jours de la période
 * d'affectation contractuelle, pas les jours d'usage effectif du
 * véhicule. La règle pipeline R-2025-002 en découle. Seules les
 * indispos réductrices R-2025-008 viennent diminuer ce numérateur.
 */
final readonly class R2025_022_ContractualPeriodVsEffectiveUsage implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-022';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Période contractuelle vs usage effectif';
    }

    public function description(): string
    {
        return "Le numérateur du prorata journalier compte le nombre de jours de la période d'affectation contractuelle (date de début → date de fin de contrat), pas le nombre de jours pendant lesquels le véhicule a effectivement circulé ou été utilisé. La doctrine BOFiP § équivalent à § 170 le précise · « le nombre de jours pendant lesquels le véhicule a effectivement circulé n'est donc pas pris en compte ». Seules les indispos réductrices R-2025-008 et la qualification LCD R-2025-021 réduisent ce numérateur. Reconduction stricte 2024.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 170',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-14',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::CadreImplicite,
            title: 'Période contractuelle vs usage effectif',
            pitch: "Le numérateur du prorata compte les jours de la période contractuelle (début → fin du contrat), pas les jours d'utilisation réelle du véhicule.",
            body: "Un contrat de 30 jours pendant lesquels le véhicule n'a roulé qu'une seule journée compte 30 jours au numérateur. La doctrine BOFiP § équivalent à § 170 le précise explicitement · « le nombre de jours pendant lesquels le véhicule a effectivement circulé n'est donc pas pris en compte ». Cette base contractuelle ne peut être réduite que par les indispos réductrices subies à la demande des pouvoirs publics (R-2025-008) ou par la qualification LCD du contrat (R-2025-021).",
            example: "Contrat 1er → 30 avril 2025 (30 j). Le véhicule n'est utilisé que les 5 et 12 avril. Au numérateur · 30 j (durée du contrat), pas 2 j (utilisation réelle).",
        );
    }
}
