<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-028 - Declaration and payment modalities, single full-year
 * 2026 version.
 *
 * Documentation-only rule. No ADR-0022 split in 2026: L. 421-159,
 * L. 421-162, L. 421-163, L. 421-164 and L. 421-165 are stable since
 * 01/03/2025 (wording from LF 2025 art. 28, LOI n°2025-127 du
 * 14/02/2025). No modification by LF 2026 nor Ordo 2025-1247 du
 * 17/12/2025.
 *
 * Strict reproduction 2025 → 2026: doctrine identical to
 * R-2025-028-bis. Declaration in January 2027 of taxes for 2026,
 * annexe n° 3310-A (régime réel normal) or formulaire n° 3517
 * (régime simplifié), no declaration if cumulated amount is zero,
 * annual recap statement kept up to date.
 */
final readonly class R2026_028_DeclarationModalities implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-028';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Modalités de déclaration et de paiement';
    }

    public function description(): string
    {
        return 'Modalités déclaratives sur la pleine année 2026 · doctrine stable depuis LF 2025 art. 28 (effet 01/03/2025). Les articles L. 421-159 à L. 421-165 n\'ont pas été modifiés par la LF 2026 ni par l\'Ordonnance n° 2025-1247 du 17/12/2025. Le redevable déclare en janvier 2027 les taxes au titre de 2026 via annexe n° 3310-A (régime réel normal) ou formulaire n° 3517 (régime simplifié). Pas de déclaration si montant cumulé nul. État récapitulatif annuel tenu à jour.';
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048637675/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-162',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602857/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-163',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602855/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-164',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-165',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602849/2026-01-01',
                'consulted_at' => '2026-05-15',
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
            title: 'Modalités de déclaration (2026)',
            pitch: 'Le redevable déclare en janvier 2027 les taxes dues au titre de 2026. Doctrine stable depuis LF 2025 art. 28 (effet 01/03/2025), reconduite sans modification en 2026.',
            body: 'La déclaration est annuelle, postée en janvier de l\'année suivante. Régime réel normal · annexe n° 3310-A à la déclaration mensuelle de TVA. Régime simplifié · formulaire n° 3517. Pas de déclaration si le montant cumulé annuel est nul. Le redevable tient à jour, en permanence, un état récapitulatif annuel des véhicules affectés à des fins économiques, conservé à disposition de l\'administration. Pas de modification 2026 vs version 2025 stabilisée.',
        );
    }
}
