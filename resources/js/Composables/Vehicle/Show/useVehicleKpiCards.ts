import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Caption dynamique de la carte KPI « Taxe réelle » : pourcentage
 * d'utilisation de l'année en cours, ou message vide si aucune
 * attribution n'a encore été enregistrée.
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
