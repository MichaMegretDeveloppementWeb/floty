import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Dynamic caption for the "Taxe réelle" KPI card: current-year usage percentage,
 * or an empty-state message when no assignment has been recorded yet.
 */
export function useVehicleKpiCards(props: {
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}): {
    actualTaxCaption: ComputedRef<string>;
} {
    const actualTaxCaption = computed<string>(() => {
        if (props.stats.daysUsedThisYear === 0 || props.stats.daysInYear === 0) {
            return "Pas encore d'utilisation";
        }

        const percent = Math.round(
            (props.stats.daysUsedThisYear / props.stats.daysInYear) * 100,
        );

        return `${percent}% d'utilisation`;
    });

    return { actualTaxCaption };
}
