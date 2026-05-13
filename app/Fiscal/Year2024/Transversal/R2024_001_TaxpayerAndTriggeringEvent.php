<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-001 · Redevable et fait générateur.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle ne participe pas au pipeline de calcul. Elle pose le
 * cadre architectural · l'entreprise utilisatrice est redevable de la
 * taxe (et non le détenteur du véhicule), et le fait générateur est
 * l'affectation du véhicule à des fins économiques.
 *
 * Toute la logique d'attribution des contrats (CompanyVehicleContract)
 * et le filtrage des couples (entreprise, véhicule) dans le pipeline
 * en découle.
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
            // Phase 13 D5.11 (audit sémantique) · qualification explicite
            // du redevable (« entreprise affectataire ») · le périmètre
            // de L. 421-95 (« véhicule affecté à des fins économiques »)
            // est défini là, mais c'est L. 421-98 qui qualifie qui
            // est redevable pour chaque cas d'affectation.
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
}
