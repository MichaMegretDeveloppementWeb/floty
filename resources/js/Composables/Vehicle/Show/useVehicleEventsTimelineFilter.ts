import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref, WritableComputedRef } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;
type FilterOption = { value: string; label: string };
type FilterChip = { key: string; label: string };

export type TimelineScopeMode = 'year' | 'period';
export type TimelineYearOption = { value: number; label: string };
export type TimelinePeriodRange = { startDate: string | null; endDate: string | null };

/** `yearModel` sentinel for the "all years" option (full history). */
export const ALL_YEARS = 0;

/** Sentinel ISO bounds for an open-ended side of a window. */
const MIN_ISO = '0000-01-01';
const MAX_ISO = '9999-12-31';

/**
 * Pure overlap test: does the event `[startDate, endDate | ∞]` intersect the
 * inclusive window `[fromIso, toIso]` ? An ongoing event (endDate null) is
 * treated as open-ended. Exported for unit testing.
 */
export function vehicleEventOverlapsWindow(
    event: Pick<VehicleEvent, 'startDate' | 'endDate'>,
    fromIso: string,
    toIso: string,
): boolean {
    const start = event.startDate;
    const end = event.endDate ?? MAX_ISO;

    return start <= toIso && end >= fromIso;
}

/**
 * Client-side year / period filter for the vehicle events timeline, mirroring
 * the Contracts Index time-scope UX (year `InlineYearSelector` ↔ period
 * `DateRangePicker` popover). The events are already fully loaded with the
 * vehicle (no extra round-trip), so filtering happens in memory: an event is
 * kept when its period overlaps the selected year or custom period (overlap,
 * not just start-date, so a multi-year event stays visible in every year it
 * spans).
 *
 * The year select offers a leading "Toutes les années" option (`ALL_YEARS`),
 * the default, so the timeline still shows the full history out of the box.
 */
export function useVehicleEventsTimelineFilter(
    events: MaybeRefOrGetter<ReadonlyArray<VehicleEvent>>,
    currentYear: number,
): {
    scopeMode: Ref<TimelineScopeMode>;
    setScopeMode: (mode: TimelineScopeMode) => void;
    yearOptions: ComputedRef<TimelineYearOption[]>;
    yearModel: WritableComputedRef<number>;
    periodRange: WritableComputedRef<TimelinePeriodRange>;
    periodOngoing: Ref<boolean>;
    pickerYear: ComputedRef<number>;
    periodLabel: ComputedRef<string>;
    periodPopoverOpen: Ref<boolean>;
    popoverRoot: Ref<HTMLElement | null>;
    isFiltered: ComputedRef<boolean>;
    activeWindow: ComputedRef<{ from: string | null; to: string | null } | null>;
    filteredEvents: ComputedRef<VehicleEvent[]>;
    selectedCategories: Ref<string[]>;
    categoryOptions: ComputedRef<FilterOption[]>;
    /** Recherche libre garage / code postal (le véhicule est déjà le contexte). */
    searchTerm: Ref<string>;
    totalAmountCents: ComputedRef<number>;
    activeAxisCount: ComputedRef<number>;
    activeFilterChips: ComputedRef<FilterChip[]>;
    removeFilterChip: (key: string) => void;
    clearAxisFilters: () => void;
} {
    const scopeMode = ref<TimelineScopeMode>('year');
    const selectedYear = ref<number>(ALL_YEARS);
    const selectedCategories = ref<string[]>([]);
    const searchTerm = ref<string>('');
    const periodStart = ref<string | null>(null);
    const periodEnd = ref<string | null>(null);
    const periodOngoing = ref<boolean>(false);
    const periodPopoverOpen = ref<boolean>(false);
    const popoverRoot = ref<HTMLElement | null>(null);

    const availableYears = computed<number[]>(() => {
        const all = toValue(events);

        if (all.length === 0) {
            return [];
        }

        let min = currentYear;
        let max = currentYear;

        for (const event of all) {
            const startYear = Number(event.startDate.slice(0, 4));
            const endYear = event.endDate === null
                ? currentYear
                : Number(event.endDate.slice(0, 4));

            if (startYear < min) {
                min = startYear;
            }

            if (endYear > max) {
                max = endYear;
            }
        }

        const range: number[] = [];

        for (let year = max; year >= min; year--) {
            range.push(year);
        }

        return range;
    });

    const yearOptions = computed<TimelineYearOption[]>(() => [
        { value: ALL_YEARS, label: 'Toutes les années' },
        ...availableYears.value.map((year) => ({ value: year, label: String(year) })),
    ]);

    // Filter suggestions = the natures actually present on this vehicle's
    // loaded events (real values, « ce qui existe vraiment »).
    const categoryOptions = computed<FilterOption[]>(() => {
        const present = new Set<string>();

        for (const event of toValue(events)) {
            for (const category of event.categories) {
                present.add(category);
            }
        }

        return [...present]
            .sort((a, b) => a.localeCompare(b, 'fr'))
            .map((category) => ({ value: category, label: category }));
    });

    /**
     * Nature axis (OR within the axis) + free search on garage / postal code
     * (contains, case-insensitive · the vehicle is already the page context),
     * applied on top of the year/period window. Empty axis = no constraint.
     */
    function matchesAxes(event: VehicleEvent): boolean {
        if (selectedCategories.value.length > 0) {
            const eventCategories = new Set(event.categories.map((c) => c.trim().toLowerCase()));
            const hit = selectedCategories.value.some((c) => eventCategories.has(c.trim().toLowerCase()));

            if (!hit) {
                return false;
            }
        }

        const term = searchTerm.value.trim().toLowerCase();

        if (term !== '') {
            const garage = (event.garage ?? '').toLowerCase();
            const postalCode = (event.postalCode ?? '').toLowerCase();

            if (!garage.includes(term) && !postalCode.includes(term)) {
                return false;
            }
        }

        return true;
    }

    const yearModel = computed<number>({
        get: () => selectedYear.value,
        set: (value: number) => {
            selectedYear.value = value;
        },
    });

    const periodRange = computed<TimelinePeriodRange>({
        get: () => ({ startDate: periodStart.value, endDate: periodEnd.value }),
        set: (range: TimelinePeriodRange) => {
            periodStart.value = range.startDate;
            periodEnd.value = range.endDate;
        },
    });

    const pickerYear = computed<number>(() => {
        if (periodStart.value) {
            return Number(periodStart.value.slice(0, 4));
        }

        return availableYears.value.length > 0 ? availableYears.value[0]! : currentYear;
    });

    const periodLabel = computed<string>(() => {
        if (!periodStart.value && !periodEnd.value) {
            return 'Aucune période sélectionnée';
        }

        const from = periodStart.value ? formatDateFr(periodStart.value) : '…';
        const to = periodEnd.value ? formatDateFr(periodEnd.value) : '…';

        return `${from} → ${to}`;
    });

    const isFiltered = computed<boolean>(() => {
        if (selectedCategories.value.length > 0 || searchTerm.value.trim() !== '') {
            return true;
        }

        if (scopeMode.value === 'period') {
            return Boolean(periodStart.value) || Boolean(periodEnd.value);
        }

        return selectedYear.value !== ALL_YEARS;
    });

    /**
     * Effective inclusive window the timeline clamps its days to. `null` when no
     * filter is active (full history). Year → `[Y-01-01, Y-12-31]`; period → the
     * entered bounds (an open side stays null).
     */
    const activeWindow = computed<{ from: string | null; to: string | null } | null>(() => {
        if (scopeMode.value === 'period') {
            if (!periodStart.value && !periodEnd.value) {
                return null;
            }

            return { from: periodStart.value || null, to: periodEnd.value || null };
        }

        if (selectedYear.value === ALL_YEARS) {
            return null;
        }

        return { from: `${selectedYear.value}-01-01`, to: `${selectedYear.value}-12-31` };
    });

    const windowEvents = computed<VehicleEvent[]>(() => {
        const all = toValue(events);

        if (scopeMode.value === 'period') {
            if (!periodStart.value && !periodEnd.value) {
                return [...all];
            }

            const from = periodStart.value || MIN_ISO;
            const to = periodOngoing.value ? MAX_ISO : (periodEnd.value || MAX_ISO);

            return all.filter((event) => vehicleEventOverlapsWindow(event, from, to));
        }

        if (selectedYear.value === ALL_YEARS) {
            return [...all];
        }

        const from = `${selectedYear.value}-01-01`;
        const to = `${selectedYear.value}-12-31`;

        return all.filter((event) => vehicleEventOverlapsWindow(event, from, to));
    });

    const filteredEvents = computed<VehicleEvent[]>(() =>
        windowEvents.value.filter((event) => matchesAxes(event)),
    );

    const totalAmountCents = computed<number>(() =>
        filteredEvents.value.reduce((sum, event) => sum + (event.amountCents ?? 0), 0),
    );

    const activeAxisCount = computed<number>(() => selectedCategories.value.length);

    const activeFilterChips = computed<FilterChip[]>(() =>
        selectedCategories.value.map((category) => ({
            key: `category:${category}`,
            label: `Nature : ${category}`,
        })),
    );

    function removeFilterChip(key: string): void {
        const separator = key.indexOf(':');
        const kind = key.slice(0, separator);
        const value = key.slice(separator + 1);

        if (kind === 'category') {
            selectedCategories.value = selectedCategories.value.filter((c) => c !== value);
        }
    }

    function clearAxisFilters(): void {
        selectedCategories.value = [];
        searchTerm.value = '';
    }

    function setScopeMode(mode: TimelineScopeMode): void {
        scopeMode.value = mode;

        if (mode === 'period') {
            // Switching to period auto-opens the picker (next logical gesture).
            periodPopoverOpen.value = true;
        } else {
            periodPopoverOpen.value = false;
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
        yearModel,
        periodRange,
        periodOngoing,
        pickerYear,
        periodLabel,
        periodPopoverOpen,
        popoverRoot,
        isFiltered,
        activeWindow,
        filteredEvents,
        selectedCategories,
        categoryOptions,
        searchTerm,
        totalAmountCents,
        activeAxisCount,
        activeFilterChips,
        removeFilterChip,
        clearAxisFilters,
    };
}
