import { computed, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type Breakdown = App.Data.User.Contract.ContractTaxBreakdownData;
type YearBreakdown = App.Data.User.Contract.ContractTaxYearBreakdownData;

/**
 * État + interactions du panel « Taxes générées par ce contrat » sur la
 * page Show contrat. Indexe les règles cross-year en `Record<code, Rule>`
 * pour résoudre en O(1) au clic d'un badge (la même règle peut apparaître
 * dans plusieurs années).
 */
export function useTaxBreakdownPanel(props: {
    taxBreakdown: Breakdown | null;
}): {
    years: ComputedRef<YearBreakdown[]>;
    isMultiYear: ComputedRef<boolean>;
    selectedCode: Ref<string | null>;
    selectedRule: ComputedRef<Rule | null>;
    modalOpen: WritableComputedRef<boolean>;
    openRule: (code: string) => void;
} {
    const years = computed<YearBreakdown[]>(() => props.taxBreakdown?.years ?? []);
    const isMultiYear = computed<boolean>(() => years.value.length > 1);

    // Indexation cross-year : si la même règle est posée en 2024 et 2025
    // pour un contrat à cheval, on garde la première occurrence trouvée
    // (les détails - nom, refs légales - sont identiques par construction
    // du registry annuel).
    const rulesByCode = computed<Record<string, Rule>>(() => {
        const map: Record<string, Rule> = {};

        for (const year of years.value) {
            for (const rule of year.appliedRules) {
                if (!(rule.ruleCode in map)) {
                    map[rule.ruleCode] = rule;
                }
            }
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

    return {
        years,
        isMultiYear,
        selectedCode,
        selectedRule,
        modalOpen,
        openRule,
    };
}
