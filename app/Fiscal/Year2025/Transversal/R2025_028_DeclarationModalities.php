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
 * R-2025-028 · Modalités de déclaration et de paiement.
 *
 * **Règle documentaire-only** · reconduction R-2024-028 avec calendrier
 * 2025 → 2026. Regroupe les 11 articles CIBS section 3 paragraphe 7
 * (L. 421-157 à L. 421-167).
 *
 * **Évolution 01/03/2025** · L. 421-159 et L. 421-164 ont une version
 * modifiée par LF 2025 art. 28 · modifications rédactionnelles, pas
 * d'impact pratique sur le redevable, l'exigibilité, le formulaire ou
 * l'état récapitulatif.
 *
 * **Calendrier 2025 → 2026** · la déclaration des taxes au titre de
 * l'année 2025 est déposée en janvier 2026 (annexe 3310-A · régime
 * réel normal, ou formulaire 3517 · régime simplifié). Floty produit
 * le récapitulatif PDF (= équivalent fiche 2857-FC-SD millésime 2025).
 */
final readonly class R2025_028_DeclarationModalities implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-028';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Modalités de déclaration et de paiement';
    }

    public function description(): string
    {
        return "L'entreprise utilisatrice redevable déclare et acquitte les taxes en janvier 2026 (au titre de 2025) via l'annexe n° 3310-A à sa déclaration de TVA (régime réel normal) ou le formulaire n° 3517 (régime simplifié). Pas de déclaration si le montant cumulé est nul. L'entreprise tient un état récapitulatif annuel, communiqué sur demande de l'administration. L. 421-159 et L. 421-164 ont une version modifiée au 01/03/2025 par LF 2025 art. 28 · modifications rédactionnelles, pas d'impact pratique. La fiche d'aide au calcul n° 2857-FC-SD millésime 2025 est produite par Floty sous forme de PDF récapitulatif.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 28;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-159',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637675/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-162',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602857/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-163',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602855/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-164',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-165',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602849/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'DGFiP n° 2857-FC-NOT-SD (Cerfa millésime 2025)',
                'url' => 'https://www.impots.gouv.fr/formulaire/2857-fc-sd/formulaire-2857-fc-sd-fiche-daide-au-calcul-des-taxes-annuelles-sur-les',
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
            section: RuleSection::CadreDeclaratif,
            title: 'Modalités de déclaration et de paiement',
            pitch: "Les taxes sont déclarées et payées par chaque entreprise utilisatrice en janvier 2026, via l'annexe 3310-A à la déclaration de TVA ou le formulaire 3517 selon son régime.",
            body: "Pas de déclaration à déposer si le montant cumulé est nul (toutes les taxes annulées par exonération). L'entreprise tient un état récapitulatif annuel des véhicules concernés, communiqué sur demande de l'administration. Floty produit le récapitulatif fiscal PDF qui correspond à la fiche d'aide au calcul n° 2857-FC-SD prévue par la DGFiP · le dépôt effectif de la déclaration reste à la charge du service comptable de chaque entreprise.",
            example: "Calendrier 2025 → 2026 · l'utilisation des véhicules pendant l'année 2025 génère une taxe qui est déclarée en janvier 2026. Floty produit le récapitulatif PDF en début d'année 2026 que le comptable utilise pour saisir l'annexe 3310-A.",
        );
    }
}
