<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Fiscal\Year2026\Classification\Concerns\PollutantCategoryAssignmentLogicTrait;
use Carbon\CarbonImmutable;

/**
 * R-2026-013 - Algorithmic pollutant categorisation (CIBS L. 421-134),
 * version 01/01 → 31/08/2026 (before Ordo 2025-1247 art. 7).
 *
 * Pipeline rule (Classification). ADR-0022: one distinct legal period
 * = one distinct Floty fiscal rule. L. 421-134 was rewritten by
 * Ordonnance n° 2025-1247 du 17/12/2025 art. 7 effective 01/09/2026
 * (pure editorial modification: removal of the "dans sa rédaction en
 * vigueur" insert). R-2026-013 covers the period BEFORE. The period
 * AFTER is carried by {@see R2026_013bis_PollutantCategoryAssignment}.
 *
 * Classify logic shared through
 * {@see PollutantCategoryAssignmentLogicTrait}: Ordo 2025-1247
 * modifications are purely editorial, the E / Cat1 / MostPolluting
 * cascade is identical on both versions.
 *
 * Legal basis:
 * - CIBS L. 421-134, version 31/12/2023 → 01/09/2026.
 */
final readonly class R2026_013_PollutantCategoryAssignment implements ClassificationRule
{
    use PollutantCategoryAssignmentLogicTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-013';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 8, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Catégorisation polluants (version 01/01 → 31/08/2026)';
    }

    public function description(): string
    {
        return 'Classement du véhicule dans les catégories E / 1 / « les plus polluants » selon motorisation et norme Euro. Version 01/01-31/08/2026 · texte L. 421-134 version 31/12/2023 → 01/09/2026 (modification rédactionnelle par Ordo 2025-1247 art. 7 sans impact matériel sur la catégorisation). La cascade applicative est strictement identique à la période 01/09-31/12/2026 (R-2026-013-bis).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 13;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-134',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Pollutants];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::Aiguillage,
            title: 'Étape 3 : quelle catégorie polluants ? (avant 01/09/2026)',
            pitch: 'Période 01/01-31/08/2026 · trois catégories E (électrique/hydrogène), 1 (essence ou gaz Euro 5/6), « plus polluants ».',
            body: 'Catégorie E = électrique exclusif, hydrogène exclusif, ou combinaison des deux. Catégorie 1 = véhicules à allumage commandé (essence, GPL, GNV, E85, ou hybride essence) respectant Euro 5 ou Euro 6. Catégorie « véhicules les plus polluants » = tous les autres, notamment tous les Diesel (même Euro 6), les essence pré-Euro 5, et les véhicules sans norme Euro renseignée. La période 01/09-31/12/2026 est portée par R-2026-013-bis · cascade applicative strictement identique.',
            example: 'Tesla Model 3 électrique → E. Peugeot 308 essence Euro 6 → 1. Renault Trafic Diesel Euro 6 → plus polluants.',
        );
    }
}
