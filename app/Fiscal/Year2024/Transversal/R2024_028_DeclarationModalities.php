<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-028 · declaration and payment modalities.
 *
 * Documentary-only rule. Groups the 11 CIBS section 3 paragraph 7
 * articles describing how the company declares and pays the annual
 * taxes (taxpayer, due date, form, annual recap, payment):
 *
 *   - L. 421-157 · persons subject to the obligations.
 *   - L. 421-158 · fiscal obligations.
 *   - L. 421-159 · taxpayer (= the assigning company).
 *   - L. 421-160 · fiscal representation obligation.
 *   - L. 421-161 · cross-cutting rules.
 *   - L. 421-162 · tax statement rules.
 *   - L. 421-163 · no declaration when the amount is zero.
 *   - L. 421-164 · annual recap.
 *   - L. 421-165 · payment rules.
 *   - L. 421-166 · audit, collection, litigation.
 *   - L. 421-167 · miscellaneous.
 *
 * Practical modalities for 2024 (DGFiP notice n° 2857-FC-NOT-SD):
 *   - Standard VAT regime · declared via appendix n° 3310-A in
 *     January 2025 (tax owed for 2024).
 *   - Non VAT taxpayers · appendix 3310-A filed by 25 January 2025.
 *   - Simplified VAT regime (RSI) · form n° 3517 filed for the
 *     financial year during which the tax became due.
 *   - Aid-to-calculation sheet · form n° 2857-FC-SD (not attached to
 *     the declaration, may be requested by the administration · the
 *     recap Floty produces as PDF).
 *   - No declaration when the amount is zero after exemptions
 *     (L. 421-163).
 *   - Keep an up-to-date annual recap, communicated on request
 *     (L. 421-164).
 *
 * Floty produces the fiscal recap PDF (equivalent of sheet
 * 2857-FC-SD) but does not file the declaration; filing remains the
 * responsibility of the using company's accounting department.
 */
final readonly class R2024_028_DeclarationModalities implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-028';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Modalités de déclaration et de paiement';
    }

    public function description(): string
    {
        return "L'entreprise utilisatrice redevable déclare et acquitte les taxes en janvier 2025 (au titre de 2024) via l'annexe n° 3310-A à sa déclaration de TVA (régime réel normal) ou le formulaire n° 3517 (régime simplifié). Pas de déclaration si le montant cumulé est nul. L'entreprise tient un état récapitulatif annuel, communiqué sur demande de l'administration. La fiche d'aide au calcul n° 2857-FC-SD n'est pas jointe à la déclaration mais peut être demandée · L'application produit exactement cette fiche sous forme de PDF récapitulatif.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637675/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-162',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602857/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-163',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602855/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-164',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-165',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602849/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            // NOTICE PDF 2857-FC-SD retirée (URL d'origine pointait sur
            // le mauvais formulaire 2042-RICI, URL canonique introuvable
            // suite restructure impots.gouv.fr). Les articles CIBS
            // L. 421-159 à 165 ci-dessus couvrent la doctrine déclarative
            // de manière opposable.
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
            pitch: 'Les taxes sont déclarées et payées par chaque entreprise utilisatrice en janvier 2025, via l\'annexe 3310-A à la déclaration de TVA ou le formulaire 3517 selon son régime.',
            body: "Pas de déclaration à déposer si le montant cumulé est nul (toutes les taxes annulées par exonération). L'entreprise tient un état récapitulatif annuel des véhicules concernés, communiqué sur demande de l'administration. L'application produit le récapitulatif fiscal PDF qui correspond à la fiche d'aide au calcul n° 2857-FC-SD prévue par la DGFiP · le dépôt effectif de la déclaration reste à la charge du service comptable de chaque entreprise.",
            example: "Calendrier 2024 → 2025 · l'utilisation des véhicules pendant l'année 2024 génère une taxe qui est déclarée en janvier 2025. L'application produit le récapitulatif PDF en début d'année 2025 que le comptable utilise pour saisir l'annexe 3310-A.",
        );
    }
}
