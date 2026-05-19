type EnergySource = App.Enums.Vehicle.EnergySource;
type EuroStandard = App.Enums.Vehicle.EuroStandard;
type UnderlyingCombustionEngineType = App.Enums.Vehicle.UnderlyingCombustionEngineType;
type PollutantCategory = App.Enums.Vehicle.PollutantCategory;

const STRICTLY_CLEAN: ReadonlyArray<EnergySource> = [
    'electric',
    'hydrogen',
    'electric_hydrogen',
];

const POSITIVE_IGNITION: ReadonlyArray<EnergySource> = [
    'gasoline',
    'lpg',
    'cng',
    'e85',
];

const HYBRID_SOURCES: ReadonlyArray<EnergySource> = [
    'plugin_hybrid',
    'non_plugin_hybrid',
];

const PRE_EURO_5: ReadonlyArray<EuroStandard> = [
    'euro_1',
    'euro_2',
    'euro_3',
    'euro_4',
];

/**
 * TS mirror of {@see App\Enums\Vehicle\PollutantCategory::derive()}.
 *
 * Used only to display the resolved category live in the form. The
 * persisted value is computed on the backend from the same inputs. Both
 * implementations must stay strictly equivalent.
 */
export function derivePollutantCategory(
    energy: EnergySource,
    euro: EuroStandard | null | '',
    underlying: UnderlyingCombustionEngineType | null | '',
): PollutantCategory {
    if (STRICTLY_CLEAN.includes(energy)) {
        return 'e';
    }

    const euroNormalized = euro === '' ? null : euro;
    const underlyingNormalized = underlying === '' ? null : underlying;

    if (
        euroNormalized !== null
        && !PRE_EURO_5.includes(euroNormalized)
        && isPositiveIgnitionOrPositiveHybrid(energy, underlyingNormalized)
    ) {
        return 'category_1';
    }

    return 'most_polluting';
}

function isPositiveIgnitionOrPositiveHybrid(
    energy: EnergySource,
    underlying: UnderlyingCombustionEngineType | null,
): boolean {
    if (POSITIVE_IGNITION.includes(energy)) {
        return true;
    }

    if (HYBRID_SOURCES.includes(energy) && underlying === 'gasoline') {
        return true;
    }

    return false;
}

/**
 * Whether the selected energy source requires the user to provide the
 * underlying combustion engine. Aligned with
 * {@see EnergySource::requiresUnderlyingCombustionEngine()} on the PHP side.
 */
export function requiresUnderlyingCombustionEngine(energy: EnergySource): boolean {
    return energy === 'plugin_hybrid'
        || energy === 'non_plugin_hybrid'
        || energy === 'electric_hydrogen';
}
