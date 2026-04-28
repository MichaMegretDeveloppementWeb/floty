import { computed, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type Breakdown = App.Data.User.Vehicle.VehicleFullYearTaxBreakdownData;

/**
 * Vue + interactions de la sidebar « Détail Coût plein » : raccourci
 * `breakdown` vers `stats.fullYearTaxBreakdown`, indexation des
 * règles par code pour résolution O(1) au clic, et état du modal
 * d'inspection d'une règle (ouverture pilotée par `selectedCode`).
 */
export function useFullYearTaxBreakdownPanel(props: {
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}): {
    breakdown: ComputedRef<Breakdown>;
    selectedCode: Ref<string | null>;
    selectedRule: ComputedRef<Rule | null>;
    modalOpen: WritableComputedRef<boolean>;
    openRule: (code: string) => void;
} {
    const breakdown = computed<Breakdown>(() => props.stats.fullYearTaxBreakdown);

    const rulesByCode = computed<Record<string, Rule>>(() => {
        const map: Record<string, Rule> = {};

        for (const rule of breakdown.value.appliedRules) {
            map[rule.ruleCode] = rule;
        }

        return map;
    });

    const selectedCode = ref<string | null>(null);

    const selectedRule = computed<Rule | null>(() => {
        if (selectedCode.value === null) {
            return null;
        }

        return rulesByCode.value[selectedCode.value] ?? null;
    });

    const modalOpen = computed<boolean>({
        get: () => selectedCode.value !== null,
        set: (value) => {
            if (!value) {
                selectedCode.value = null;
            }
        },
    });

    const openRule = (code: string): void => {
        selectedCode.value = code;
    };

    return { breakdown, selectedCode, selectedRule, modalOpen, openRule };
}
