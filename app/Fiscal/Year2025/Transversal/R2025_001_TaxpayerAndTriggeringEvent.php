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
 * R-2025-001 · Redevable et fait générateur.
 *
 * **Règle documentaire-only** · reconduction stricte de R-2024-001.
 * Textes CIBS L. 421-95 / L. 421-98 / L. 421-99 inchangés depuis 2022 ·
 * BOFiP `BOI-AIS-MOB-10-30-10-20250528` reconduit. L'entreprise
 * utilisatrice est redevable au titre de l'affectation à des fins
 * économiques.
 */
final readonly class R2025_001_TaxpayerAndTriggeringEvent implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-001';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Redevable et fait générateur';
    }

    public function description(): string
    {
        return "Définit le périmètre du fait générateur (affectation d'un véhicule à des fins économiques · CIBS L. 421-95), la qualification de l'entreprise affectataire (= redevable · CIBS L. 421-98) et le cas particulier des véhicules pris en location (CIBS L. 421-99). L. 421-95 et L. 421-98 ont été modifiés par LF 2025 art. 28 au 01/03/2025 · modifications rédactionnelles, pas d'impact pratique sur la doctrine du redevable. L. 421-99 inchangé depuis 01/01/2022.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            // L. 421-95 a deux versions en 2025 (modifié 01/03/2025 par
            // LF 2025 art. 28 · modifications rédactionnelles, pas
            // d'impact pratique sur la doctrine de l'affectation).
            [
                'type' => 'CIBS',
                'article' => 'L. 421-95 (version 01/01/2025 → 28/02/2025)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-95 (version 01/03/2025 → 31/12/2025)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            // L. 421-98 idem · deux versions en 2025.
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98 (version 01/01/2025 → 28/02/2025)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98 (version 01/03/2025 → 31/12/2025)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-99',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603043/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-10',
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
            title: 'Redevable et fait générateur',
            pitch: "Le redevable est l'entreprise utilisatrice effective du véhicule. Le fait générateur est l'affectation du véhicule à ses besoins économiques.",
            body: "Quand le véhicule est en stock chez le bailleur, personne n'est redevable (exonération loueur R-020). Quand le véhicule est attribué à une entreprise utilisatrice, c'est elle qui devient redevable, au prorata de son utilisation.",
        );
    }
}
