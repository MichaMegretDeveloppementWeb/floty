import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed } from 'vue';
import { toValue } from 'vue';

type EnergySource = App.Enums.Vehicle.EnergySource;
type EuroStandard = App.Enums.Vehicle.EuroStandard;
type UnderlyingCombustionEngineType = App.Enums.Vehicle.UnderlyingCombustionEngineType;
type PollutantCategory = App.Enums.Vehicle.PollutantCategory;

/**
 * Crit'Air vignette: optional input on the vehicle form.
 * Not yet persisted: the composable is ready when the column is added to
 * `vehicle_fiscal_characteristics`.
 */
export type CritAirVignette = 'E' | '1' | '2' | '3' | '4' | '5' | 'unclassified';

export type CritAirCheckInput = {
    energySource: EnergySource | null;
    euroStandard: EuroStandard | null;
    underlyingCombustionEngineType: UnderlyingCombustionEngineType | null;
    critAirVignette: CritAirVignette | null;
};

export type CritAirCheckResult = {
    /** Pollutant category inferred by R-2024-013 (may be null if data is insufficient). */
    inferredPollutantCategory: PollutantCategory | null;
    /** Pollutant category expected from the Crit'Air vignette. */
    expectedFromCritAir: PollutantCategory | null;
    /** True when a mismatch is detected; non-blocking UI alert. */
    hasMismatch: boolean;
    /** French banner message, or `null` when no alert. */
    message: string | null;
};

/**
 * R-2024-024 Crit'Air sanity check (CIBS BOFiP § 270).
 *
 * Compares the pollutant category inferred from R-2024-013 (engine + Euro) with the category
 * expected from the Crit'Air vignette entered. On mismatch, returns a banner message under
 * the vehicle form's "Caractéristiques fiscales" section.
 *
 * Non-blocking: never disables form submission. User can save despite the alert
 * (the entered data is authoritative). Coherence diagnostic.
 *
 * Replicates the backend R-2024-013 cascade in TS for real-time validation without HTTP round-trip.
 * Keep both implementations in sync: any R-013 PHP change requires a manual audit here.
 */
export function useCritAirCheck(
    input: MaybeRefOrGetter<CritAirCheckInput>,
): ComputedRef<CritAirCheckResult> {
    return computed(() => {
        const data = toValue(input);
        const inferred = inferPollutantCategory(data);
        const expected = expectedCategoryFromCritAir(data.critAirVignette);

        if (inferred === null || expected === null || inferred === expected) {
            return {
                inferredPollutantCategory: inferred,
                expectedFromCritAir: expected,
                hasMismatch: false,
                message: null,
            };
        }

        return {
            inferredPollutantCategory: inferred,
            expectedFromCritAir: expected,
            hasMismatch: true,
            message: `Incohérence Crit'Air détectée : la motorisation déduit la catégorie « ${labelOf(inferred)} », mais la vignette Crit'Air saisie correspond à « ${labelOf(expected)} ». Vérifiez la saisie ou contactez le support si la vignette est correcte.`,
        };
    });
}

/**
 * R-2024-013 cascade (see App\Fiscal\Year2024\Classification\R2024_013_PollutantCategoryAssignment).
 */
function inferPollutantCategory(input: CritAirCheckInput): PollutantCategory | null {
    if (input.energySource === null) {
        return null;
    }

    if (
        input.energySource === 'electric'
        || input.energySource === 'hydrogen'
        || input.energySource === 'electric_hydrogen'
    ) {
        return 'e';
    }

    if (input.euroStandard === null) {
        return null;
    }

    if (!isEuro5OrAbove(input.euroStandard)) {
        return 'most_polluting';
    }

    const isPositiveIgnition = ['gasoline', 'lpg', 'cng', 'e85'].includes(input.energySource);
    const isHybridGasoline =
        ['plugin_hybrid', 'non_plugin_hybrid'].includes(input.energySource)
        && input.underlyingCombustionEngineType === 'gasoline';

    return isPositiveIgnition || isHybridGasoline ? 'category_1' : 'most_polluting';
}

function isEuro5OrAbove(standard: EuroStandard): boolean {
    return ['euro_1', 'euro_2', 'euro_3', 'euro_4'].indexOf(standard) === -1;
}

function expectedCategoryFromCritAir(vignette: CritAirVignette | null): PollutantCategory | null {
    if (vignette === null) {
        return null;
    }

    return {
        E: 'e' as const,
        '1': 'category_1' as const,
        '2': 'most_polluting' as const,
        '3': 'most_polluting' as const,
        '4': 'most_polluting' as const,
        '5': 'most_polluting' as const,
        unclassified: 'most_polluting' as const,
    }[vignette];
}

function labelOf(category: PollutantCategory): string {
    return {
        e: 'E · Électrique / hydrogène',
        category_1: '1 · Essence ou gaz Euro 5/6',
        most_polluting: 'Véhicules les plus polluants',
    }[category];
}
