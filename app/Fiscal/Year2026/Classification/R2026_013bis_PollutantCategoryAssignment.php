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
 * R-2026-013-bis · Catégorisation polluants algorithmique (CIBS
 * L. 421-134) · **version 01/09 → 31/12/2026** (après Ordo 2025-1247
 * art. 7).
 *
 * **Règle pipeline (Classification)** · pendant de
 * {@see R2026_013_PollutantCategoryAssignment} qui couvre 01/01-31/08.
 * Texte L. 421-134 réécrit par l'Ordonnance n° 2025-1247 du 17/12/2025
 * (art. 7 + art. 49 entrée en vigueur) à effet du 01/09/2026 ·
 * modifications **rédactionnelles pures** (suppression de l'incise
 * « dans sa rédaction en vigueur ») sans changement matériel du
 * périmètre E / Cat1 / MostPolluting. Logique partagée via
 * {@see PollutantCategoryAssignmentLogicTrait}.
 *
 * **Base légale** ·
 * - CIBS L. 421-134 · version 01/09/2026 → en vigueur (audité Chrome
 *   live 15/05/2026).
 * - Ordo 2025-1247 du 17/12/2025 art. 7 (réécriture rédactionnelle) +
 *   art. 49 (entrée en vigueur 01/09/2026).
 */
final readonly class R2026_013bis_PollutantCategoryAssignment implements ClassificationRule
{
    use PollutantCategoryAssignmentLogicTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-013-bis';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 9, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'Catégorisation polluants (version 01/09 → 31/12/2026, modif Ordo 2025-1247 art. 7)';
    }

    public function description(): string
    {
        return 'Classement du véhicule dans les catégories E / 1 / « les plus polluants » sur la période 01/09-31/12/2026, version rédactionnellement nettoyée de L. 421-134 par Ordo 2025-1247 art. 7 (suppression de l\'incise « dans sa rédaction en vigueur »). Modification purement rédactionnelle · cascade applicative E / Cat1 / MostPolluting identique à la période 01/01-31/08/2026 (R-2026-013).';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        // Doit suivre R-2026-013 dans l'ordre d'affichage.
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844542/2026-09-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'ORDONNANCE',
                'reference' => 'Ordonnance n° 2025-1247 du 17 décembre 2025 portant recodification de la taxe sur la valeur ajoutée et diverses modifications du code des impositions sur les biens et services · art. 7 (réécriture rédactionnelle L. 421-134) + art. 49 (entrée en vigueur 01/09/2026)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000053091516',
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
            tab: RuleTab::Calcul,
            section: RuleSection::Aiguillage,
            title: 'Étape 3 : quelle catégorie polluants ? (depuis 01/09/2026)',
            pitch: 'Période 01/09-31/12/2026 · même cascade E / Cat1 / MostPolluting que la période précédente, avec rédaction modernisée de L. 421-134 par Ordo 2025-1247 art. 7.',
            body: "Doctrine inchangée vs R-2026-013 (01/01-31/08/2026) · trois catégories E (électrique/hydrogène), 1 (essence ou gaz Euro 5/6), « plus polluants ». La logique d'aiguillage est strictement identique entre les deux périodes · seul le texte de référence L. 421-134 a été réécrit (suppression de l'incise « dans sa rédaction en vigueur » dans le 2°).",
            example: 'Tesla Model 3 électrique → E. Peugeot 308 essence Euro 6 → 1. Renault Trafic Diesel Euro 6 → plus polluants.',
        );
    }
}
