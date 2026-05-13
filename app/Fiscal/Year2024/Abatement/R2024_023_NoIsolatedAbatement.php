<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Abatement;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;

/**
 * R-2024-023 · Aucun abattement isolé applicable en 2024.
 *
 * **Règle documentaire-only** (ADR-0022 · complément Phase 13 D5.11) ·
 * cette règle documente l'**absence** d'abattement isolé applicable
 * en 2024 (la catégorie Abatement est vide cette année). Le tableau
 * `legalBasis()` est volontairement vide · aucun article du CIBS ni
 * paragraphe BOFiP ne fonde un abattement isolé en 2024 (lecture
 * exhaustive du CIBS et de la doctrine confirmée).
 *
 * Conservée pour rester visible sur la page « Règles de calcul » et
 * matérialiser le constat « pas d'abattement en 2024 » plutôt que
 * laisser un silence trompeur.
 */
final readonly class R2024_023_NoIsolatedAbatement implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-023';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Aucun abattement isolé applicable en 2024';
    }

    public function description(): string
    {
        return '2024 : aucun abattement isolé (ex. E85) applicable aux deux taxes annuelles. Confirmé par lecture exhaustive du CIBS et du BOFiP.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Abatement;
    }

    public function displayOrder(): int
    {
        return 23;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }
}
