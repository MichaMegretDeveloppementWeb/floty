<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;

/**
 * R-2024-016 — Exonération CO₂ électrique / hydrogène (CIBS L. 421-124).
 *
 * Véhicules à motorisation strictement électrique, hydrogène ou
 * combinaison électrique + hydrogène : exonération **CO₂ uniquement**
 * (la taxe polluants reste due si le véhicule n'est pas catégorie E).
 *
 * Sémantique préservée : le tarif CO₂ annuel plein est mis à 0 dans le
 * breakdown (au lieu d'être calculé puis non appliqué).
 */
final readonly class R2024_016_ElectricHydrogen implements ExemptionRule
{
    public function ruleCode(): string
    {
        return 'R-2024-016';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        $energy = $context->currentFiscalCharacteristics?->energy_source;
        if ($energy === null) {
            return ExemptionVerdict::notExempt();
        }

        $isElectric = match ($energy) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };

        if ($isElectric) {
            return ExemptionVerdict::onlyCo2(
                'Exonération électrique/hydrogène (CIBS L. 421-124)',
                $this->ruleCode(),
            );
        }

        return ExemptionVerdict::notExempt();
    }
}
