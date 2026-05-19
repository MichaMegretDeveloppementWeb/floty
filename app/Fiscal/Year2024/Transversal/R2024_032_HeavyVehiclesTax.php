<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-032 · annual tax on heavy freight vehicles (former axle tax).
 *
 * Documentary-only rule, OUT OF FLOTY SCOPE.
 *
 * Annual tax distinct from the two Floty taxes (passenger CO₂ and
 * pollutants), applicable to heavy freight vehicles (N2, N3 and some
 * O4 trailers) whose maximum technically permissible mass is
 * ≥ 12 tonnes.
 *
 * Taxpayer: the using company of the heavy vehicle (equivalent sense
 * to the passenger taxes).
 *
 * Why out of Floty scope: Floty's vehicle scope is strictly limited
 * to categories M1 (passenger cars) and N1 (light commercial
 * ≤ 3.5 t). Vehicles ≥ 12 t (N2/N3/O4) are out of scope by
 * construction.
 *
 * Documented for fiscal completeness: if a Floty using company also
 * runs heavy vehicles outside the Floty fleet, it is liable for this
 * distinct tax but Floty does not compute it.
 */
final readonly class R2024_032_HeavyVehiclesTax implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2024-032';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Taxe annuelle sur les véhicules lourds de transport de marchandises';
    }

    public function description(): string
    {
        return "Taxe annuelle distincte des deux taxes l'application, applicable aux véhicules lourds de transport de marchandises ≥ 12 tonnes (catégories N2, N3 et certaines remorques O4). Tarif fonction du nombre d'essieux, de la masse en charge maximale et de la présence d'une suspension pneumatique. Hors périmètre de l'application · le périmètre véhicule de l'application est limité aux catégories M1 et N1 ≤ 3,5 t. Une entreprise utilisatrice de l'application qui exploite par ailleurs des véhicules lourds doit déclarer cette taxe distinctement.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 32;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-145 à L. 421-156',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602907/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-100 (définition véhicules lourds)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637683/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-30',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13962-PGP.html/identifiant=BOI-AIS-MOB-10-30-30-20250528',
                'consulted_at' => '2026-04-22',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'Taxe véhicules lourds',
                'url' => 'https://www.impots.gouv.fr/taxe-annuelle-sur-les-vehicules-lourds-de-transport-de-marchandises',
                'consulted_at' => '2026-04-22',
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: 'Taxe sur les véhicules lourds de transport de marchandises',
            pitch: "Taxe annuelle spécifique aux camions ≥ 12 tonnes (N2/N3) · structurellement hors du périmètre de l'application (M1/N1 uniquement).",
            body: "L'application couvre les voitures de tourisme (M1) et les utilitaires légers ≤ 3,5 t (N1). Les véhicules lourds (camions, semi-remorques) sont taxés selon un régime distinct (CIBS L. 421-145 et suivants) et ne rentrent pas dans le périmètre métier de l'application. Une entreprise utilisatrice de l'application qui exploite aussi des poids lourds doit gérer cette taxe en dehors de l'application.",
        );
    }
}
