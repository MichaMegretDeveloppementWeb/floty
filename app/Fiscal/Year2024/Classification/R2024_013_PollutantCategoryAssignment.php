<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2024-013 — Catégorisation polluants algorithmique
 * (CIBS art. L. 421-134).
 *
 * Cascade :
 *   - **E** : électrique / hydrogène / combinaison strictement non
 *     thermique
 *   - **Catégorie 1** : essence, GPL, GNV, E85 (allumage commandé) en
 *     Euro 5 ou 6 ; OU hybride à sous-jacent essence en Euro 5/6
 *   - **Most polluting** : tout le reste (Diesel, essence pré-Euro 5,
 *     hybride à sous-jacent Diesel, sans norme Euro)
 *
 * Cette règle **remplace** la lecture du champ stocké
 * `pollutant_category` qui était posée par R-2024-005 (avant 1.9). Le
 * champ stocké reste écrit par les seeders / formulaires pour compat,
 * mais c'est cette classification qui fait foi côté pipeline.
 */
final readonly class R2024_013_PollutantCategoryAssignment implements ClassificationRule
{
    public function ruleCode(): string
    {
        return 'R-2024-013';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Pollutants];
    }

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        return $context
            ->withResolvedPollutantCategory($this->categorize($fiscal))
            ->withAppliedRule($this->ruleCode());
    }

    private function categorize(VehicleFiscalCharacteristics $fiscal): PollutantCategory
    {
        // Catégorie E : motorisation strictement non polluante
        if ($this->isStrictlyClean($fiscal->energy_source)) {
            return PollutantCategory::E;
        }

        // Catégorie 1 : allumage commandé (essence/GPL/GNV/E85) Euro 5/6
        // OU hybride à sous-jacent essence Euro 5/6
        if (
            $fiscal->euro_standard !== null
            && $fiscal->euro_standard->isEuro5OrAbove()
            && $this->isPositiveIgnitionOrPositiveHybrid($fiscal->energy_source, $fiscal->underlying_combustion_engine_type)
        ) {
            return PollutantCategory::Category1;
        }

        return PollutantCategory::MostPolluting;
    }

    private function isStrictlyClean(EnergySource $source): bool
    {
        return match ($source) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };
    }

    private function isPositiveIgnitionOrPositiveHybrid(
        EnergySource $source,
        ?UnderlyingCombustionEngineType $underlying,
    ): bool {
        // Allumage commandé pur
        if (in_array($source, [EnergySource::Gasoline, EnergySource::Lpg, EnergySource::Cng, EnergySource::E85], true)) {
            return true;
        }

        // Hybride à sous-jacent essence
        if (
            in_array($source, [EnergySource::PluginHybrid, EnergySource::NonPluginHybrid], true)
            && $underlying === UnderlyingCombustionEngineType::Gasoline
        ) {
            return true;
        }

        return false;
    }
}
