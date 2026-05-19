<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Pollutant emissions category (CIBS L. 421-134). Derived from energy source,
 * Euro standard, and underlying combustion engine type (for hybrids); never
 * user-input. The {@see self::derive()} cascade is the single source of truth
 * for both repository writes and the R-2024-013 fiscal computation.
 *
 * - `E`: strictly electric / hydrogen / combination.
 * - `Category1`: positive-ignition (gasoline/LPG/CNG/E85, or gasoline-underlying hybrid) Euro 5/6.
 * - `MostPolluting`: anything else.
 */
enum PollutantCategory: string
{
    case E = 'e';
    case Category1 = 'category_1';
    case MostPolluting = 'most_polluting';

    /**
     * Pedagogical label (category + eligibility criterion, no tariff). The category
     * is year-independent; year-scoped tariffs live in the fiscal rules pages (ADR-0022).
     */
    public function label(): string
    {
        return match ($this) {
            self::E => 'E - Électrique / hydrogène',
            self::Category1 => '1 - Essence ou gaz Euro 5/6',
            self::MostPolluting => 'Véhicules les plus polluants',
        };
    }

    /**
     * Classification cascade (CIBS L. 421-134). Single source of truth shared by the
     * Repository (auto-set on write), R-2024-013 (computation) and the frontend mirror
     * `derivePollutantCategory.ts` (live form feedback).
     *
     * Guarantees: a thermal vehicle without Euro standard falls to `MostPolluting`;
     * a hybrid without known `underlyingCombustion` falls to `MostPolluting` (safe default).
     */
    public static function derive(
        EnergySource $energy,
        ?EuroStandard $euro,
        ?UnderlyingCombustionEngineType $underlying,
    ): self {
        if (self::isStrictlyClean($energy)) {
            return self::E;
        }

        if (
            $euro !== null
            && $euro->isEuro5OrAbove()
            && self::isPositiveIgnitionOrPositiveHybrid($energy, $underlying)
        ) {
            return self::Category1;
        }

        return self::MostPolluting;
    }

    private static function isStrictlyClean(EnergySource $source): bool
    {
        return match ($source) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };
    }

    private static function isPositiveIgnitionOrPositiveHybrid(
        EnergySource $source,
        ?UnderlyingCombustionEngineType $underlying,
    ): bool {
        if (in_array($source, [
            EnergySource::Gasoline,
            EnergySource::Lpg,
            EnergySource::Cng,
            EnergySource::E85,
        ], true)) {
            return true;
        }

        if (
            in_array($source, [
                EnergySource::PluginHybrid,
                EnergySource::NonPluginHybrid,
            ], true)
            && $underlying === UnderlyingCombustionEngineType::Gasoline
        ) {
            return true;
        }

        return false;
    }
}
