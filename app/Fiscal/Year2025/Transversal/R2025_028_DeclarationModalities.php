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
 * R-2025-028 · Modalités de déclaration et de paiement · **version
 * 01/01 → 28/02/2025** (avant LF 2025 art. 28).
 *
 * **Règle documentaire-only** · ADR-0022 · une période légale distincte =
 * une règle fiscale Floty distincte. L. 421-159 et L. 421-164 ont été
 * réécrits par LF 2025 art. 28 à effet du 01/03/2025. R-2025-028 couvre
 * la période **avant** cette modification. La période **après** est
 * portée par {@see R2025_028bis_DeclarationModalities}.
 */
final readonly class R2025_028_DeclarationModalities implements InformativeRule
{
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-028';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 2, 28, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Modalités de déclaration et de paiement (version 01/01 → 28/02/2025)';
    }

    public function description(): string
    {
        return 'Modalités déclaratives sur la période 01/01-28/02/2025, avant la réécriture rédactionnelle des L. 421-159 et L. 421-164 par LF 2025 art. 28 à effet du 01/03/2025. Cadre opposable inchangé dans la doctrine · le redevable (entreprise utilisatrice) déclare en janvier 2026 les taxes au titre de 2025 via annexe n° 3310-A (régime réel normal) ou formulaire n° 3517 (régime simplifié). Pas de déclaration si montant cumulé nul. État récapitulatif annuel tenu à jour. La période 01/03-31/12/2025 est portée par R-2025-028-bis.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637675/2025-01-01',
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-165',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602849/2025-01-01',
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
            title: 'Modalités de déclaration (avant LF 2025 art. 28)',
            pitch: 'Période 01/01-28/02/2025 · le redevable déclare en janvier 2026 les taxes au titre de 2025 (annexe 3310-A ou formulaire 3517).',
            body: "Pas de déclaration si montant cumulé nul. État récapitulatif annuel tenu à jour, communiqué sur demande de l'administration. L'application produit le récapitulatif PDF (équivalent fiche 2857-FC-SD millésime 2025). La période 01/03-31/12/2025 (textes réécrits par LF 2025 art. 28) est portée par R-2025-028-bis · doctrine inchangée mais URLs distinctes.",
        );
    }
}
