<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-007 - Vehicle characteristics historisation.
 *
 * Documentation-only rule, strict reproduction of R-2024-007. For each
 * assignment day, the version of vehicle fiscal characteristics
 * effective at that date is used for the calculation. The segmented
 * executor (`FiscalSegmentedExecutor`) materialises this doctrine by
 * splitting the pipeline by VFC-effective sub-period (ADR-0021).
 */
final readonly class R2025_007_VehicleCharacteristicsHistorization implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-007';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Historisation des caractéristiques véhicule';
    }

    public function description(): string
    {
        return "Application de la version effective des caractéristiques fiscales à chaque jour d'affectation. Reconduction stricte 2024 (principe architectural ADR-0021).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 7;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-164',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214908/2025-01-01',
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
            section: RuleSection::CadreInterne,
            title: 'Historisation des caractéristiques véhicule',
            pitch: "Les caractéristiques fiscales d'un véhicule sont datées · une correction de CO₂ prend effet à sa date d'application, sans rétroactivité sur les déclarations passées.",
            body: "Chaque véhicule a un historique de ses caractéristiques fiscales (motorisation, CO₂, norme Euro, flag E85). Le calcul d'une attribution utilise les caractéristiques effectives à la date de cette attribution. Si une donnée est corrigée (erreur de saisie), l'ancienne valeur reste visible pour audit et reproductibilité des déclarations déjà envoyées.",
        );
    }
}
