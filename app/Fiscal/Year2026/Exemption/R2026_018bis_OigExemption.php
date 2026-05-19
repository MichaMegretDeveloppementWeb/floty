<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2026-018-bis - "Certain forms of operation" OIG exemption, version
 * 01/09 → 31/12/2026 (after Ordo 2025-1247 art. 4).
 *
 * Pipeline rule (Exemption) INACTIVE by default. ADR-0022:
 * counterpart to {@see R2026_018_OigExemption} which covers
 * 01/01-31/08.
 *
 * Pure editorial modification by Ordo 2025-1247 art. 4: the reference
 * to articles 261 and 261-7 CGI is replaced by a reference to
 * articles L. 213-104 to L. 213-110, L. 213-113, L. 213-115,
 * L. 213-116 and L. 213-120 CIBS (VAT recodification into the new
 * Book II of CIBS created by the same ordinance). No material impact
 * on the scope of exempt OIGs.
 *
 * Mechanics strictly identical to R-2026-018: logic lives in
 * `evaluate()` which returns `notExempt()` (inactive rule). If OIG
 * becomes active one day, the same applicative logic will apply on
 * both versions (only the codified references differ).
 *
 * Inactive by default in V1: no current Floty user company is OIG.
 */
final readonly class R2026_018bis_OigExemption implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2026-018-bis';
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
        return "Exonération organisme d'intérêt général (version 01/09 → 31/12/2026, modif Ordo 2025-1247 art. 4)";
    }

    public function description(): string
    {
        return 'OIG · exonération CO₂ et polluants sur la période 01/09-31/12/2026, version réécrite par Ordo 2025-1247 art. 4 (recodification TVA · référence CGI 261 → CIBS L. 213-104 à L. 213-120). Modification purement rédactionnelle · périmètre OIG inchangé vs la période 01/01-31/08/2026 (R-2026-018). INACTIVE par défaut.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        // Must follow R-2026-018 in display order.
        return 18;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-126',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602965/2026-09-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-138',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602927/2026-09-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'ORDONNANCE',
                'reference' => 'Ordo 2025-1247 art. 4 + 49',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000053091516',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    public function isActive(): bool
    {
        return false;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::ExonerationInactive,
            title: 'Exonération organisme d\'intérêt général (depuis 01/09/2026)',
            pitch: 'Période 01/09-31/12/2026 · même périmètre OIG que la période précédente, avec rédaction modernisée par Ordo 2025-1247 (recodification TVA).',
            body: "Doctrine inchangée vs R-2026-018 (01/01-31/08/2026) · l'exonération vise les véhicules détenus par une association 1901, fondation, etc., affectés exclusivement à l'activité non lucrative. La logique applicative est strictement identique entre les deux périodes · seul le texte de référence L. 421-126 / L. 421-138 a été réécrit (suppression de la référence au CGI 261, remplacée par des références au CIBS L. 213-XXX, suite à la recodification de la TVA dans le nouveau Livre II du CIBS).",
        );
    }
}
