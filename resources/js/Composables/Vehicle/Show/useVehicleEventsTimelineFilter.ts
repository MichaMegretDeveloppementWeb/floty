import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref, WritableComputedRef } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { vehicleEventTypeShortLabel } from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;
type VehicleEventType = App.Enums.VehicleEvent.VehicleEventType;
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
    selectedTypes: Ref<string[]>;
    selectedCategories: Ref<string[]>;
    typeOptions: ComputedRef<FilterOption[]>;
    categoryOptions: ComputedRef<FilterOption[]>;
    totalAmountCents: ComputedRef<number>;
    activeAxisCount: ComputedRef<number>;
    activeFilterChips: ComputedRef<FilterChip[]>;
    removeFilterChip: (key: string) => void;
    clearAxisFilters: () => void;
} {
    const scopeMode = ref<TimelineScopeMode>('year');
    const selectedYear = ref<number>(ALL_YEARS);
    const selectedTypes = ref<string[]>([]);
    const selectedCategories = ref<string[]>([]);
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

    // Filter suggestions = the type / category values actually present on this
    // vehicle's loaded events (real values, « ce qui existe vraiment »). Custom
    // events (`other`) surface by their individual title, mirroring categories,
    // never under a single generic « Personnalisé » bucket.
    const typeOptions = computed<FilterOption[]>(() => {
        const knownTypes = new Set<string>();
        const customTitles = new Set<string>();

        for (const event of toValue(events)) {
            if (event.type === 'other') {
                // System lifecycle markers (read-only) belong to « Cycle de vie »,
                // not the user's custom types · they never seed the type axis.
                if (!event.isReadOnly && event.title !== null && event.title !== '') {
                    customTitles.add(event.title);
                }
            } else {
                knownTypes.add(event.type);
            }
        }

        const knownOptions = [...knownTypes].sort().map((value) => ({
            value,
            label: vehicleEventTypeShortLabel[value as VehicleEventType] ?? value,
        }));
        const customOptions = [...customTitles]
            .sort((a, b) => a.localeCompare(b, 'fr'))
            .map((title) => ({ value: title, label: title }));

        return [...knownOptions, ...customOptions];
    });

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
     * Type + category axes (OR within an axis, AND between axes), applied on top
     * of the year/period window. Empty axis = no constraint on that axis.
     */
    function matchesAxes(event: VehicleEvent): boolean {
        if (selectedTypes.value.length > 0) {
            // Custom events are matched by their title (the suggestion value),
            // known events by their enum type.
            const typeValue = event.type === 'other' ? (event.title ?? '') : event.type;

            if (!selectedTypes.value.includes(typeValue)) {
                return false;
            }
        }

        if (selectedCategories.value.length > 0) {
            const eventCategories = new Set(event.categories.map((c) => c.trim().toLowerCase()));
            const hit = selectedCategories.value.some((c) => eventCategories.has(c.trim().toLowerCase()));

            if (!hit) {
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
        if (selectedTypes.value.length > 0 || selectedCategories.value.length > 0) {
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

    const activeAxisCount = computed<number>(
        () => selectedTypes.value.length + selectedCategories.value.length,
    );

    const activeFilterChips = computed<FilterChip[]>(() => {
        const chips: FilterChip[] = [];

        for (const type of selectedTypes.value) {
            chips.push({
                key: `type:${type}`,
                label: `Type : ${vehicleEventTypeShortLabel[type as VehicleEventType] ?? type}`,
            });
        }

        for (const category of selectedCategories.value) {
            chips.push({ key: `category:${category}`, label: `Catégorie : ${category}` });
        }

        return chips;
    });

    function removeFilterChip(key: string): void {
        const separator = key.indexOf(':');
        const kind = key.slice(0, separator);
        const value = key.slice(separator + 1);

        if (kind === 'type') {
            selectedTypes.value = selectedTypes.value.filter((t) => t !== value);
        } else if (kind === 'category') {
            selectedCategories.value = selectedCategories.value.filter((c) => c !== value);
        }
    }

    function clearAxisFilters(): void {
        selectedTypes.value = [];
        selectedCategories.value = [];
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
        selectedTypes,
        selectedCategories,
        typeOptions,
        categoryOptions,
        totalAmountCents,
        activeAxisCount,
        activeFilterChips,
        removeFilterChip,
        clearAxisFilters,
    };
}
