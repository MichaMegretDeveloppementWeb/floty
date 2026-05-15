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
/**
 * Détecte si une scission s'applique à la dimension donnée (CO₂ ou
 * polluants) sur une année · vrai si plusieurs segments **ET** les
 * tarifs diffèrent d'un segment à l'autre. Permet à l'UI de basculer
 * en mode multi-fenêtres uniquement quand cela apporte de l'info
 * pédagogique (segmentation neutre tarifairement = mono affichage).
 */
export function hasScission(
    year: YearBreakdown,
    dimension: 'co2' | 'pollutants',
): boolean {
    if (year.segments.length <= 1) {
        return false;
    }

    const key =
        dimension === 'co2' ? 'co2FullYearTariff' : 'pollutantsFullYearTariff';
    // segments.length > 1 vérifié au début · segments[0] existe forcément.
    const firstTariff = year.segments[0]![key];

    return year.segments.some((s) => s[key] !== firstTariff);
}

/**
 * Formate une plage de dates ISO `YYYY-MM-DD` en `JJ/MM → JJ/MM` pour
 * un affichage compact en cellule monospace.
 */
export function formatSegmentRange(from: string, to: string): string {
    const f = new Date(from);
    const t = new Date(to);
    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${pad(f.getDate())}/${pad(f.getMonth() + 1)} → ${pad(t.getDate())}/${pad(t.getMonth() + 1)}`;
}

export function useTaxBreakdownPanel(props: {
    taxBreakdown: Breakdown | null;
}): {
    years: ComputedRef<YearBreakdown[]>;
    isMultiYear: ComputedRef<boolean>;
    selectedCode: Ref<string | null>;
    selectedYear: Ref<number | null>;
    selectedRule: ComputedRef<Rule | null>;
    modalOpen: WritableComputedRef<boolean>;
    openRule: (code: string, year: number) => void;
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
    // Conservé pour le filtre "rule appartient à cette année" appliqué
    // au modal de détail (cf. v-if dans TaxBreakdownPanel.vue). Les URLs
    // officielles ne dépendent plus de cet état · elles vivent dans la
    // règle PHP et arrivent prêtes à l'emploi via les props Inertia
    // (ADR-0022 finalisée · Phase 13 D5.11).
    const selectedYear = ref<number | null>(null);

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
                selectedYear.value = null;
            }
        },
    });

    const openRule = (code: string, year: number): void => {
        selectedCode.value = code;
        selectedYear.value = year;
    };

    return {
        years,
        isMultiYear,
        selectedCode,
        selectedYear,
        selectedRule,
        modalOpen,
        openRule,
    };
}
