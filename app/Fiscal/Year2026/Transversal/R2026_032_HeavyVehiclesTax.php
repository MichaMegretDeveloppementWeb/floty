<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-032 · Taxe annuelle sur les véhicules lourds de transport de
 * marchandises (ex-taxe à l'essieu).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Reconduction stricte R-2025-032. Taxe annuelle distincte des deux
 * taxes Floty (CO₂ et polluants tourisme), applicable aux véhicules
 * **lourds** de transport de marchandises (catégories N2, N3 et
 * certaines remorques O4) ≥ 12 tonnes. Paramètres 2026 inchangés.
 *
 * **Pourquoi hors périmètre Floty** · le périmètre véhicule Floty est
 * strictement limité aux catégories **M1 (voitures de tourisme) et
 * N1 (utilitaires légers ≤ 3,5 t)**. Les véhicules ≥ 12 t sont
 * structurellement hors champ.
 *
 * **Audit Chrome live 15/05/2026** · CIBS L. 421-145 « En vigueur
 * depuis le 01/01/2022 » · stable, non modifié par LF 2026 ni Ordo
 * 2025-1247.
 */
final readonly class R2026_032_HeavyVehiclesTax implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2026-032';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Taxe annuelle sur les véhicules lourds de transport de marchandises';
    }

    public function description(): string
    {
        return "Taxe annuelle distincte des deux taxes l'application, applicable aux véhicules lourds de transport de marchandises ≥ 12 tonnes (catégories N2, N3 et certaines remorques O4). Tarif fonction du nombre d'essieux, de la masse en charge maximale et de la présence d'une suspension pneumatique. Paramètres 2026 inchangés vs 2025. Hors périmètre de l'application · le périmètre véhicule de l'application est limité aux catégories M1 et N1 ≤ 3,5 t. Une entreprise utilisatrice de l'application qui exploite par ailleurs des véhicules lourds doit déclarer cette taxe distinctement.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602907/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-100 (définition véhicules lourds)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637683/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-30',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13962-PGP.html/identifiant=BOI-AIS-MOB-10-30-30-20250528',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'impots.gouv.fr · Taxe annuelle véhicules lourds marchandises',
                'url' => 'https://www.impots.gouv.fr/taxe-annuelle-sur-les-vehicules-lourds-de-transport-de-marchandises',
                'consulted_at' => '2026-05-15',
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
            tab: RuleTab::Connexe,
            section: RuleSection::TaxeConnexe,
            title: 'Taxe sur les véhicules lourds de transport de marchandises',
            pitch: "Taxe annuelle spécifique aux camions ≥ 12 tonnes (N2/N3) · structurellement hors du périmètre de l'application (M1/N1 uniquement).",
            body: "L'application couvre les voitures de tourisme (M1) et les utilitaires légers ≤ 3,5 t (N1). Les véhicules lourds (camions, semi-remorques) sont taxés selon un régime distinct (CIBS L. 421-145 et suivants) et ne rentrent pas dans le périmètre métier de l'application. Une entreprise utilisatrice de l'application qui exploite aussi des poids lourds doit gérer cette taxe en dehors de l'application.",
        );
    }
}
