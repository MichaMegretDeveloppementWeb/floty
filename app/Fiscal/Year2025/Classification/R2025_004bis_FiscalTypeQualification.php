<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Fiscal\Year2025\Classification\Concerns\FiscalTypeQualificationLogicTrait;
use Carbon\CarbonImmutable;

/**
 * R-2025-004-bis · Qualification du type fiscal (M1 / N1) · **version
 * 01/03 → 31/12/2025** (après LF 2025 art. 28).
 *
 * **Règle pipeline (Classification)** · pendant de
 * {@see R2025_004_FiscalTypeQualification} qui couvre 01/01-28/02. Texte
 * L. 421-2 réécrit par la LOI n°2025-127 du 14/02/2025 à effet du
 * 01/03/2025 · modifications rédactionnelles sans changement matériel
 * du périmètre M1/N1. Logique partagée via
 * {@see FiscalTypeQualificationLogicTrait}.
 */
final readonly class R2025_004bis_FiscalTypeQualification implements ClassificationRule
{
    use FiscalTypeQualificationLogicTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-004-bis';
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
        return 'Qualification M1 / N1 (version 01/03 → 31/12/2025, modif LF 2025 art. 28)';
    }

    public function description(): string
    {
        return 'Classification du type fiscal du véhicule sur la période 01/03-31/12/2025, version réécrite de L. 421-2 par LF 2025 art. 28 (LOI n°2025-127 du 14/02/2025). Modifications rédactionnelles · cascade applicative M1/N1 identique à la période 01/01-28/02/2025 (R-2025-004). Frontière M1 (VP) vs N1 (VU), cas particuliers N1 ≥ 5 places. Complément CIBS L. 421-97 inchangé.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        // Doit suivre R-2025-004 dans l'ordre d'affichage.
        return 4;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-2 (version 01/03/2025 → 31/12/2025, modif LF 2025 art. 28)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844510/2025-03-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-97 (inchangé en 2025)',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196667/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LOI n°2025-127 du 14 février 2025 art. 28 (réécriture L. 421-2 à effet 01/03/2025)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051183558',
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
            tab: RuleTab::Calcul,
            section: RuleSection::Aiguillage,
            title: 'Étape 1 : le véhicule est-il taxable ? (depuis LF 2025 art. 28)',
            pitch: 'Période 01/03-31/12/2025 · même cascade M1/N1 que la période précédente, avec rédaction modernisée de L. 421-2 par LF 2025 art. 28.',
            body: "Doctrine inchangée vs R-2025-004 (01/01-28/02/2025) · les véhicules M1 et certains N1 sont assujettis aux deux taxes. La logique d'aiguillage est strictement identique entre les deux périodes · seul le texte de référence L. 421-2 a été réécrit (modifications rédactionnelles).",
            example: 'Renault Master N1 fourgon de marchandises → hors taxes. Peugeot Partner N1 « camionnette 2 rangs » → taxable.',
        );
    }
}
