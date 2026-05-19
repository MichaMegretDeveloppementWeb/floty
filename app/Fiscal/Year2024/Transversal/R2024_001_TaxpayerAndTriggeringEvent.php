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
 * R-2024-001 · taxpayer and triggering event.
 *
 * Documentary-only rule (ADR-0022). Does not participate in the
 * calculation pipeline. Sets the architectural framework: the using
 * company is the taxpayer (not the vehicle holder), and the
 * triggering event is the assignment of the vehicle to economic
 * purposes.
 *
 * All contract assignment logic (CompanyVehicleContract) and pair
 * filtering in the pipeline derive from this.
 */
final readonly class R2024_001_TaxpayerAndTriggeringEvent implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-001';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Redevable et fait générateur';
    }

    public function description(): string
    {
        return "Définit le périmètre du fait générateur (affectation d'un véhicule à des fins économiques · CIBS L. 421-95), la qualification de l'entreprise affectataire (= redevable · CIBS L. 421-98) et le cas particulier des véhicules pris en location (CIBS L. 421-99).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-95',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214924/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // L. 421-98 explicitly qualifies who is the taxpayer for
            // each assignment case. L. 421-95 defines the scope
            // ("vehicle assigned to economic purposes").
            [
                'type' => 'CIBS',
                'article' => 'L. 421-98',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000051214920/2024-06-01',
                'consulted_at' => '2026-05-13',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-99',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044603043/2024-06-01',
                'consulted_at' => '2026-05-06',
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
            section: RuleSection::CadreImplicite,
            title: 'Redevable et fait générateur',
            pitch: 'Le redevable est l’entreprise utilisatrice effective du véhicule. Le fait générateur est l’affectation du véhicule à ses besoins économiques.',
            body: 'Quand le véhicule est en stock chez le bailleur, personne n’est redevable (exonération loueur R-020). Quand le véhicule est attribué à une entreprise utilisatrice, c’est elle qui devient redevable, au prorata de son utilisation.',
        );
    }
}
