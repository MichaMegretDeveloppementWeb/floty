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
 * R-2026-009 - Mid-year decommissioning.
 *
 * Documentation-only rule, strict reproduction of R-2025-009. Time
 * proratisation of assignment for a vehicle exiting the fleet during
 * the year (transfer, destruction). The exit date
 * (`vehicles.exit_date`) bounds the assignment from above: contracts
 * cannot overflow past this date (ADR-0018 rev. 1.1).
 *
 * CIBS L. 421-107 stable since 01/01/2022, not modified by LF 2026
 * nor Ordo 2025-1247.
 */
final readonly class R2026_009_MidYearDecommissioning implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-009';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return "Mise hors-service en cours d'année";
    }

    public function description(): string
    {
        return "Un véhicule cédé ou détruit en cours d'année n'est plus affecté à des fins économiques à compter de sa date de sortie. La proportion annuelle d'affectation est ramenée à la fraction d'année pendant laquelle l'entreprise détenait effectivement le véhicule (date de 1ère immatriculation par l'entreprise → date de sortie). Reconduction stricte 2025 → 2026.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 9;
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 190',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20250528',
                'consulted_at' => '2026-05-15',
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
            section: RuleSection::CadreEvenement,
            title: "Mise hors-service en cours d'année",
            pitch: "Un véhicule vendu ou détruit en cours d'année n'est plus taxable à compter de sa date de sortie de flotte.",
            body: "Si un véhicule sort de la flotte le 30 juin 2026, aucune attribution postérieure à cette date n'est taxable. La proportion annuelle d'affectation est ramenée à la fraction d'année pendant laquelle l'entreprise détenait effectivement le véhicule. La doctrine BOFiP illustre ce principe avec l'exemple d'une entreprise qui acquiert un véhicule au 31 janvier et le revend au 30 novembre · la proportion annuelle vaut alors 304/365 = 83,3 % (2026, année non bissextile).",
        );
    }
}
