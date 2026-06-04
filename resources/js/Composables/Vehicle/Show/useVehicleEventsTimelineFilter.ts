import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref, WritableComputedRef } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

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
    filteredEvents: ComputedRef<VehicleEvent[]>;
} {
    const scopeMode = ref<TimelineScopeMode>('year');
    const selectedYear = ref<number>(ALL_YEARS);
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
        if (scopeMode.value === 'period') {
            return Boolean(periodStart.value) || Boolean(periodEnd.value);
        }

        return selectedYear.value !== ALL_YEARS;
    });

    const filteredEvents = computed<VehicleEvent[]>(() => {
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
        filteredEvents,
    };
}
