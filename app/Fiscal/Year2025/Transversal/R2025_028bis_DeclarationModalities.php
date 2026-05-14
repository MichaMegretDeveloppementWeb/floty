<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2025-028-bis · Modalités de déclaration et de paiement · **version
 * 01/03 → 31/12/2025** (après LF 2025 art. 28).
 *
 * **Règle documentaire-only** · pendant de {@see R2025_028_DeclarationModalities}
 * qui couvre 01/01-28/02. ADR-0022 · une période légale distincte = une
 * règle fiscale Floty distincte.
 *
 * **Modifications LF 2025 art. 28** · L. 421-159 et L. 421-164 ont été
 * réécrits par la LOI n°2025-127 du 14/02/2025 à effet du 01/03/2025.
 * Modifications rédactionnelles · pas d'impact pratique sur le redevable,
 * l'exigibilité, le formulaire ou l'état récapitulatif.
 */
final readonly class R2025_028bis_DeclarationModalities implements InformativeRule
{
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-028-bis';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Modalités de déclaration et de paiement (version 01/03 → 31/12/2025, modif LF 2025 art. 28)';
    }

    public function description(): string
    {
        return 'Modalités déclaratives sur la période 01/03-31/12/2025, version réécrite des L. 421-159 et L. 421-164 par LF 2025 art. 28 à effet du 01/03/2025. Modifications rédactionnelles · doctrine inchangée vs R-2025-028. Le redevable déclare en janvier 2026 les taxes au titre de 2025 via annexe n° 3310-A (régime réel normal) ou formulaire n° 3517 (régime simplifié). Pas de déclaration si montant cumulé nul. État récapitulatif annuel tenu à jour.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        // Doit suivre R-2025-028 dans l'ordre d'affichage.
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
                'type' => 'LOI',
                'reference' => 'LOI n°2025-127 du 14 février 2025 art. 28 (réécriture L. 421-159/164 à effet 01/03/2025)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051168007',
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
            title: 'Modalités de déclaration (depuis LF 2025 art. 28)',
            pitch: 'Période 01/03-31/12/2025 · même doctrine déclarative que la période précédente avec rédaction modernisée des L. 421-159 et L. 421-164 par LF 2025 art. 28.',
            body: 'Cette règle couvre la période où L. 421-159 et L. 421-164 sont dans leur version réécrite par la LOI n°2025-127 du 14/02/2025 à effet du 01/03/2025. La doctrine reste identique vs R-2025-028 (qui couvre 01/01-28/02/2025) · même redevable, même calendrier, même formulaires. Les modifications légales sont purement rédactionnelles.',
        );
    }
}
