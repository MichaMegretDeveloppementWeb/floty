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
 * R-2025-004 · Qualification du type fiscal (M1 / N1) · **version
 * 01/01 → 28/02/2025** (avant LF 2025 art. 28).
 *
 * **Règle pipeline (Classification)** · ADR-0022 · une période légale
 * distincte = une règle fiscale Floty distincte. L. 421-2 a été réécrit
 * par LF 2025 art. 28 à effet du 01/03/2025. R-2025-004 couvre la
 * période **avant**. La période **après** est portée par
 * {@see R2025_004bis_FiscalTypeQualification}.
 *
 * Logique de classify partagée via {@see FiscalTypeQualificationLogicTrait}
 * (modifications LF 2025 purement rédactionnelles · cascade M1/N1
 * identique sur les 2 versions).
 *
 * Cascade · M1 sans usage spécial → taxable ; N1 pick-up ≥ 5 places non
 * skiable → taxable ; N1 camionnette ≥ 2 rangs affectée transport de
 * personnes → taxable ; sinon non taxable (court-circuit).
 *
 * **Complément CIBS L. 421-97 · véhicules réputés non affectés** · par
 * dérogation à L. 421-95, un véhicule autorisé à circuler pour les seuls
 * besoins de sa construction, commercialisation, réparation ou contrôle
 * technique (régime W garage) est réputé ne pas être affecté à des fins
 * économiques. Inchangé en 2025. Hors flotte Floty par construction.
 */
final readonly class R2025_004_FiscalTypeQualification implements ClassificationRule
{
    use FiscalTypeQualificationLogicTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-004';
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
        return 'Qualification M1 / N1 (version 01/01 → 28/02/2025)';
    }

    public function description(): string
    {
        return 'Classification du type fiscal du véhicule sur la période 01/01-28/02/2025, avant la réécriture rédactionnelle de L. 421-2 par LF 2025 art. 28 à effet du 01/03/2025. Frontière M1 (VP) vs N1 (VU), cas particuliers N1 ≥ 5 places. La cascade applicative est identique à celle de la période 01/03-31/12/2025 (R-2025-004-bis). Complément CIBS L. 421-97 · véhicules en circulation pour les seuls besoins de leur construction, commercialisation, réparation ou contrôle technique (régime W garage) sont réputés ne pas être affectés à des fins économiques.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
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
                'article' => 'L. 421-2',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844510/2025-01-01',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-97',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196667/2025-01-01',
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
            section: RuleSection::Aiguillage,
            title: 'Étape 1 : le véhicule est-il taxable ? (avant LF 2025 art. 28)',
            pitch: 'Période 01/01-28/02/2025 · seuls les véhicules M1 (tourisme) et certains N1 (transport de personnes) sont assujettis aux deux taxes.',
            body: "L'application qualifie automatiquement chaque véhicule à partir de sa catégorie de réception européenne (M1/N1), de sa carrosserie et du nombre de places. Les vrais utilitaires de transport de marchandises (N1 type fourgon, camionnette à 1 rang) sont hors du champ des taxes. La période 01/03-31/12/2025 est portée par R-2025-004-bis · même cascade applicative.",
            example: 'Renault Master N1 fourgon de marchandises → hors taxes. Peugeot Partner N1 « camionnette 2 rangs » → taxable.',
        );
    }
}
