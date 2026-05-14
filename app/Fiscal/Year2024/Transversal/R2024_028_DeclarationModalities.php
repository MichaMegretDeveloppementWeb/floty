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
 * R-2024-028 · Modalités de déclaration et de paiement.
 *
 * **Règle documentaire-only** · regroupe les 11 articles CIBS section 3
 * paragraphe 7 qui décrivent comment l'entreprise déclare et acquitte
 * les taxes annuelles (redevable, exigibilité, formulaire, état
 * récapitulatif, paiement) :
 *
 *   - L. 421-157 · règles de personnes soumises aux obligations.
 *   - L. 421-158 · obligations fiscales.
 *   - L. 421-159 · redevable de la taxe (= entreprise affectataire).
 *   - L. 421-160 · obligation de représentation fiscale.
 *   - L. 421-161 · règles transverses.
 *   - L. 421-162 · règles de constatation des taxes.
 *   - L. 421-163 · pas de déclaration si montant nul.
 *   - L. 421-164 · état récapitulatif annuel.
 *   - L. 421-165 · règles de paiement.
 *   - L. 421-166 · contrôle, recouvrement, contentieux.
 *   - L. 421-167 · divers.
 *
 * **Modalités pratiques 2024** (cf. notice DGFiP n° 2857-FC-NOT-SD) :
 *   - Régime réel normal TVA · déclaration sur annexe n° 3310-A en
 *     janvier 2025 (taxe due au titre de 2024).
 *   - Non-redevables TVA · annexe 3310-A déposée au plus tard le 25
 *     janvier 2025.
 *   - Régime simplifié TVA (RSI) · formulaire n° 3517 à déposer au
 *     titre de l'exercice au cours duquel la taxe est devenue exigible.
 *   - Fiche d'aide au calcul · formulaire n° 2857-FC-SD (non joint à
 *     la déclaration, peut être demandé par l'administration · c'est
 *     le récapitulatif que Floty produit en PDF).
 *   - Pas de déclaration si montant nul après exonérations
 *     (L. 421-163).
 *   - État récapitulatif annuel à tenir à jour, communiqué sur demande
 *     de l'administration (L. 421-164).
 *
 * Floty produit le récapitulatif fiscal PDF (= équivalent fiche 2857-FC-SD)
 * mais ne dépose pas la déclaration · ce dépôt reste à la charge du
 * service comptable de chaque entreprise utilisatrice.
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
        return 'L\'entreprise utilisatrice redevable déclare et acquitte les taxes en janvier 2025 (au titre de 2024) via l\'annexe n° 3310-A à sa déclaration de TVA (régime réel normal) ou le formulaire n° 3517 (régime simplifié). Pas de déclaration si le montant cumulé est nul. L\'entreprise tient un état récapitulatif annuel, communiqué sur demande de l\'administration. La fiche d\'aide au calcul n° 2857-FC-SD n\'est pas jointe à la déclaration mais peut être demandée · Floty produit exactement cette fiche sous forme de PDF récapitulatif.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602853/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-165',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602849/2024-06-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'DGFiP n° 2857-FC-NOT-SD (Cerfa 52374#03)',
                'url' => 'https://www.impots.gouv.fr/sites/default/files/formulaires/2857-fc-sd/2024/2857-fc-sd_4888.pdf',
                'consulted_at' => '2026-04-22',
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
            pitch: 'Les taxes sont déclarées et payées par chaque entreprise utilisatrice en janvier 2025, via l\'annexe 3310-A à la déclaration de TVA ou le formulaire 3517 selon son régime.',
            body: 'Pas de déclaration à déposer si le montant cumulé est nul (toutes les taxes annulées par exonération). L\'entreprise tient un état récapitulatif annuel des véhicules concernés, communiqué sur demande de l\'administration. Floty produit le récapitulatif fiscal PDF qui correspond à la fiche d\'aide au calcul n° 2857-FC-SD prévue par la DGFiP · le dépôt effectif de la déclaration reste à la charge du service comptable de chaque entreprise.',
            example: 'Calendrier 2024 → 2025 · l\'utilisation des véhicules pendant l\'année 2024 génère une taxe qui est déclarée en janvier 2025. Floty produit le récapitulatif PDF en début d\'année 2025 que le comptable utilise pour saisir l\'annexe 3310-A.',
        );
    }
}
