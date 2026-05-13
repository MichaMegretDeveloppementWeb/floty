<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-009 · Mise hors-service en cours d'année.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle énonce le principe de proratisation temporelle de
 * l'affectation pour un véhicule sorti du parc en cours d'année
 * (cession, destruction). La date de sortie (`vehicles.exit_date`)
 * borne l'affectation supérieure ; les contrats ne peuvent pas
 * déborder cette date (cf. ADR-0018 rev. 1.1).
 */
final readonly class R2024_009_MidYearDecommissioning implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-009';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return "Mise hors-service en cours d'année";
    }

    public function description(): string
    {
        return "Un véhicule cédé ou détruit en cours d'année n'est plus affecté à des fins économiques à compter de sa date de sortie. La proportion annuelle d'affectation est ramenée à la fraction d'année pendant laquelle l'entreprise détenait effectivement le véhicule (date de 1ère immatriculation par l'entreprise → date de sortie). BOFiP § 190 explicite l'exemple d'une entreprise qui acquiert un véhicule au 31/01 et le revend au 30/11.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603019/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
                'paragraph' => '§ 190',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13932-PGP.html/identifiant=BOI-AIS-MOB-10-30-10-20240710#180_4641',
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
