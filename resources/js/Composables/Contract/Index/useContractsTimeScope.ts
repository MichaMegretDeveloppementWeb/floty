/**
 * Sélecteur scope hybride année/période pour l'Index Contracts
 * (chantier J ADR-0020 + remediation Vague 1 Lot 4 D12 · F-34-008).
 *
 * 2 modes mutuellement exclusifs côté URL ·
 *  - mode 'year'   · `?year=YYYY` → SelectInput compact
 *  - mode 'period' · `?periodStart=&periodEnd=` → DateRangePicker en popover
 *
 * Le mode initial est dérivé des params URL (`year` présent → year ;
 * `periodStart/End` présents → period ; sinon défaut `year`).
 *
 * Gère également :
 *  - l'état d'ouverture du popover période + click-outside + Escape
 *  - le label affiché sur le bouton-pill période
 *  - la `pickerYear` (année à laquelle ancrer le DateRangePicker)
 *
 * Sortie utilisée tel-quel par `pages/User/Contracts/Index/Index.vue`
 * comme orchestrateur pur.
 */

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';
import type { ContractFilters } from '@/Composables/Contract/Index/useContractsTable';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { formatDateFr } from '@/Utils/format/formatDateFr';

export type ScopeMode = 'year' | 'period';

export type YearOption = { value: number; label: string };

export type PeriodRange = { startDate: string | null; endDate: string | null };

export type UseContractsTimeScopeOptions = {
    /** Filters initiaux issus du DTO query backend (pour résoudre le mode initial). */
    initialQuery: Pick<ContractFilters, 'year' | 'periodStart' | 'periodEnd'>;
    /** Liste des années disponibles, source du `defaultYear` et de `yearOptions`. */
    availableYears: ComputedRef<readonly number[]>;
    /** Accès aux filtres temporels dans le `useServerTableState`. */
    tableState: ServerTableState<ContractFilters>;
};

export type UseContractsTimeScopeReturn = {
    scopeMode: Ref<ScopeMode>;
    setScopeMode: (mode: ScopeMode) => void;
    yearOptions: ComputedRef<YearOption[]>;
    defaultYear: ComputedRef<number>;
    yearModel: WritableComputedRef<number>;
    periodRange: WritableComputedRef<PeriodRange>;
    periodOngoing: Ref<boolean>;
    pickerYear: ComputedRef<number>;
    periodLabel: ComputedRef<string>;
    periodPopoverOpen: Ref<boolean>;
    popoverRoot: Ref<HTMLElement | null>;
};

/**
 * Résout le mode initial à partir des params URL hydratés sur le DTO query.
 */
export function resolveInitialScopeMode(
    initialQuery: Pick<ContractFilters, 'year' | 'periodStart' | 'periodEnd'>,
): ScopeMode {
    if (initialQuery.year !== null) {
        return 'year';
    }

    if (initialQuery.periodStart !== null || initialQuery.periodEnd !== null) {
        return 'period';
    }

    return 'year';
}

export function useContractsTimeScope(
    options: UseContractsTimeScopeOptions,
): UseContractsTimeScopeReturn {
    const { initialQuery, availableYears, tableState } = options;

    const scopeMode = ref<ScopeMode>(resolveInitialScopeMode(initialQuery));
    const periodOngoing = ref<boolean>(false);
    const periodPopoverOpen = ref<boolean>(false);
    const popoverRoot = ref<HTMLElement | null>(null);

    const yearOptions = computed<YearOption[]>(() =>
        availableYears.value.map((year) => ({ value: year, label: String(year) })),
    );

    const defaultYear = computed<number>(() => {
        if (initialQuery.year !== null) {
            return initialQuery.year;
        }

        const years = availableYears.value;

        return years.length === 0
            ? new Date().getFullYear()
            : Math.max(...years);
    });

    const yearModel = computed<number>({
        get: () => tableState.filters.value.year ?? defaultYear.value,
        set: (v: number) => {
            // En mode year · on set year, on efface periodStart/End. patchFilters
            // pour update atomique en 1 seul reload.
            tableState.patchFilters({
                year: v,
                periodStart: null,
                periodEnd: null,
            });
        },
    });

    const periodRange = computed<PeriodRange>({
        get: () => ({
            startDate: tableState.filters.value.periodStart,
            endDate: tableState.filters.value.periodEnd,
        }),
        set: (range: PeriodRange) => {
            tableState.patchFilters({
                year: null,
                periodStart: range.startDate,
                periodEnd: range.endDate,
            });
        },
    });

    const pickerYear = computed<number>(() => {
        const start = tableState.filters.value.periodStart;

        if (start !== null) {
            return Number.parseInt(start.slice(0, 4), 10);
        }

        return defaultYear.value;
    });

    const periodLabel = computed<string>(() => {
        const start = tableState.filters.value.periodStart;
        const end = tableState.filters.value.periodEnd;

        if (start === null && end === null) {
            return 'Aucune période sélectionnée';
        }

        const s = start === null ? '…' : formatDateFr(start);
        const e = end === null ? '…' : formatDateFr(end);

        return `${s} → ${e}`;
    });

    function setScopeMode(mode: ScopeMode): void {
        scopeMode.value = mode;

        if (mode === 'year') {
            // Bascule en année · applique l'année par défaut, efface period
            // et referme le popover si ouvert.
            periodPopoverOpen.value = false;

            if (tableState.filters.value.year === null) {
                tableState.patchFilters({
                    year: defaultYear.value,
                    periodStart: null,
                    periodEnd: null,
                });
            }
        } else {
            // Bascule en période · garde period si déjà saisi, sinon clear year
            if (
                tableState.filters.value.periodStart === null
                && tableState.filters.value.periodEnd === null
            ) {
                tableState.patchFilters({
                    year: null,
                    periodStart: null,
                    periodEnd: null,
                });
            } else {
                tableState.setFilter('year', null);
            }

            // Auto-ouvre le date picker au passage en mode période · c'est
            // le geste suivant logique (l'utilisateur veut saisir une plage)
            // et le popover se ferme facilement (clic dehors / Escape).
            periodPopoverOpen.value = true;
        }
    }

    function handleDocumentMouseDown(event: MouseEvent): void {
        if (!periodPopoverOpen.value) {
            return;
        }

        const target = event.target as Node | null;

        if (target === null) {
            return;
        }

        if (popoverRoot.value !== null && popoverRoot.value.contains(target)) {
            return;
        }

        periodPopoverOpen.value = false;
    }

    function handleEscape(event: KeyboardEvent): void {
        if (event.key === 'Escape' && periodPopoverOpen.value) {
            periodPopoverOpen.value = false;
        }
    }

    onMounted(() => {
        document.addEventListener('mousedown', handleDocumentMouseDown);
        document.addEventListener('keydown', handleEscape);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('mousedown', handleDocumentMouseDown);
        document.removeEventListener('keydown', handleEscape);
    });

    return {
        scopeMode,
        setScopeMode,
        yearOptions,
        defaultYear,
        yearModel,
        periodRange,
        periodOngoing,
        pickerYear,
        periodLabel,
        periodPopoverOpen,
        popoverRoot,
    };
}
