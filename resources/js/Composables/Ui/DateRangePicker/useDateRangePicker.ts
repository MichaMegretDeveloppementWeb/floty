import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type DateRange = {
    startDate: string | null;
    endDate: string | null;
};

export type DayCell = {
    iso: string;
    day: number;
    inMonth: boolean;
    disabled: boolean;
    isStart: boolean;
    isEnd: boolean;
    isInRange: boolean;
    inCurrentWeek: boolean;
};

export type SelectOption<T extends string | number> = {
    value: T;
    label: string;
};

const FRENCH_MONTH_FORMATTER = new Intl.DateTimeFormat('fr-FR', {
    month: 'long',
});
const DAYS_IN_WEEK = 7;
const CALENDAR_ROWS = 6;
const YEAR_RANGE_HALF = 5;

/**
 * Format a JS `Date` as local ISO `YYYY-MM-DD` (not UTC, to avoid
 * timezone-induced day shifts).
 */
export function formatIso(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/**
 * `2024-05-15` -> `15/05/2024` for French user display.
 */
export function formatFr(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

/**
 * Strict ISO `YYYY-MM-DD` validation: exact format, real date,
 * identical round-trip (rejects `2024-02-30`).
 */
export function isValidIsoDate(s: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        return false;
    }

    const d = new Date(`${s}T00:00:00`);

    if (Number.isNaN(d.getTime())) {
        return false;
    }

    return formatIso(d) === s;
}

/**
 * Sort two ISO dates and return `[min, max]`. Used wherever bound
 * order may be inverted (second click earlier, end input < start).
 */
export function normalizeRange(a: string, b: string): [string, string] {
    return a <= b ? [a, b] : [b, a];
}

/**
 * ISO dates within `[start, end]` (inclusive) that intersect
 * `disabledSet`. Empty result means no conflict.
 */
export function rangeConflicts(
    start: string,
    end: string,
    disabledSet: ReadonlySet<string>,
): string[] {
    const conflicts: string[] = [];
    const a = new Date(`${start}T00:00:00`);
    const b = new Date(`${end}T00:00:00`);
    const cur = new Date(a);

    while (cur <= b) {
        const iso = formatIso(cur);

        if (disabledSet.has(iso)) {
            conflicts.push(iso);
        }

        cur.setDate(cur.getDate() + 1);
    }

    return conflicts;
}

/**
 * Longest consecutive sub-range inside `[start, end]` containing no
 * date from `disabledSet`. Returns `null` if every date is disabled.
 *
 * On equal-length ties, returns the earliest sub-range (deterministic,
 * "start as soon as possible" default).
 */
export function findLongestFreeSubrange(
    start: string,
    end: string,
    disabledSet: ReadonlySet<string>,
): { start: string; end: string } | null {
    let bestStart: string | null = null;
    let bestEnd: string | null = null;
    let bestLen = 0;

    let curStart: string | null = null;
    let curEnd: string | null = null;
    let curLen = 0;

    const finalize = (): void => {
        if (curStart !== null && curEnd !== null && curLen > bestLen) {
            bestStart = curStart;
            bestEnd = curEnd;
            bestLen = curLen;
        }
    };

    const a = new Date(`${start}T00:00:00`);
    const b = new Date(`${end}T00:00:00`);
    const cur = new Date(a);

    while (cur <= b) {
        const iso = formatIso(cur);

        if (disabledSet.has(iso)) {
            finalize();
            curStart = null;
            curEnd = null;
            curLen = 0;
        } else {
            if (curStart === null) {
                curStart = iso;
            }

            curEnd = iso;
            curLen += 1;
        }

        cur.setDate(cur.getDate() + 1);
    }

    finalize();

    return bestStart !== null && bestEnd !== null
        ? { start: bestStart, end: bestEnd }
        : null;
}

/**
 * State + handlers for `DateRangePicker`. The template delegates entirely.
 */
export function useDateRangePicker(
    yearProp: Readonly<Ref<number>>,
    startMonthProp: Readonly<Ref<number>>,
    disabledDatesProp: Readonly<Ref<readonly string[]>>,
    range: Ref<DateRange>,
    ongoing: Ref<boolean>,
    highlightDatesProp: Readonly<Ref<readonly string[]>> = ref<readonly string[]>([]),
): {
    currentYear: Ref<number>;
    currentMonth: Ref<number>;
    errorMessage: Ref<string | null>;
    monthLabel: ComputedRef<string>;
    monthOptions: ComputedRef<SelectOption<number>[]>;
    yearOptions: ComputedRef<SelectOption<number>[]>;
    weeks: ComputedRef<DayCell[][]>;
    summary: ComputedRef<string>;
    disabledSet: ComputedRef<Set<string>>;
    gotoPrevMonth: () => void;
    gotoNextMonth: () => void;
    setMonth: (month: number) => void;
    setYear: (year: number) => void;
    onDayClick: (cell: DayCell) => void;
    onStartDateInput: (iso: string) => void;
    onEndDateInput: (iso: string) => void;
    clearSelection: () => void;
} {
    const currentYear = ref<number>(yearProp.value);
    const currentMonth = ref<number>(startMonthProp.value);
    const errorMessage = ref<string | null>(null);

    const disabledSet = computed<Set<string>>(
        () => new Set(disabledDatesProp.value),
    );

    const highlightSet = computed<Set<string>>(
        () => new Set(highlightDatesProp.value),
    );

    // Ongoing toggled on -> drop the end date if present.
    watch(ongoing, (value) => {
        if (value) {
            range.value = { startDate: range.value.startDate, endDate: null };
            errorMessage.value = null;
        }
    });

    // Parent may dynamically drive the calendar window (e.g. opening an
    // edit modal on a May entry should land in May, not January). Without
    // these watchers `currentYear`/`currentMonth` would stick to the
    // first-mount value.
    watch(yearProp, (value) => {
        currentYear.value = value;
    });
    watch(startMonthProp, (value) => {
        currentMonth.value = value;
    });

    // Failsafe: keep the calendar pinned to whichever bound just changed,
    // regardless of origin (native input, calendar click, programmatic
    // set by parent, preset, etc.). Without this watcher, editing the
    // end date via input to a different month sometimes left the
    // calendar stuck on the previous month: manual `jumpToIsoMonth`
    // calls in input handlers were occasionally shunted by Vue's
    // reactivity cycle when the parent re-rendered.
    //
    // Bound being followed is the one that actually changed between
    // prev and next: the user expects their latest edit to be reflected
    // (edit start -> jump to start, edit end -> jump to end). If both
    // change at once (reset or initial set), endDate wins.
    watch(
        () => [range.value.startDate, range.value.endDate] as const,
        ([nextStart, nextEnd], prev) => {
            const [prevStart, prevEnd] = prev ?? [null, null];
            const startChanged = nextStart !== prevStart;
            const endChanged = nextEnd !== prevEnd;

            const target = endChanged && nextEnd !== null
                ? nextEnd
                : startChanged && nextStart !== null
                    ? nextStart
                    : nextEnd ?? nextStart;

            if (target === null) {
                return;
            }

            const targetYear = Number(target.slice(0, 4));
            const targetMonth = Number(target.slice(5, 7));

            if (
                targetYear !== currentYear.value
                || targetMonth !== currentMonth.value
            ) {
                currentYear.value = targetYear;
                currentMonth.value = targetMonth;
            }
        },
    );

    const monthLabel = computed<string>(() => {
        const date = new Date(currentYear.value, currentMonth.value - 1, 1);

        return date.toLocaleDateString('fr-FR', {
            month: 'long',
            year: 'numeric',
        });
    });

    const monthOptions = computed<SelectOption<number>[]>(() => {
        const opts: SelectOption<number>[] = [];

        for (let m = 1; m <= 12; m++) {
            const d = new Date(2024, m - 1, 1);
            const label = FRENCH_MONTH_FORMATTER.format(d);
            opts.push({
                value: m,
                label: label.charAt(0).toUpperCase() + label.slice(1),
            });
        }

        return opts;
    });

    // Rolling +/-5 years around the year prop.
    const yearOptions = computed<SelectOption<number>[]>(() => {
        const center = yearProp.value;
        const opts: SelectOption<number>[] = [];

        for (let y = center - YEAR_RANGE_HALF; y <= center + YEAR_RANGE_HALF; y++) {
            opts.push({ value: y, label: String(y) });
        }

        return opts;
    });

    const weeks = computed<DayCell[][]>(() => {
        const year = currentYear.value;
        const monthIdx = currentMonth.value - 1;

        const firstOfMonth = new Date(year, monthIdx, 1);
        const jsDayOfWeek = firstOfMonth.getDay(); // 0 = Sunday
        const leading = (jsDayOfWeek + 6) % 7; // Monday = 0

        const gridStart = new Date(year, monthIdx, 1 - leading);
        const rows: DayCell[][] = [];

        const start = range.value.startDate;
        const end = range.value.endDate;

        for (let row = 0; row < CALENDAR_ROWS; row++) {
            const week: DayCell[] = [];

            for (let col = 0; col < DAYS_IN_WEEK; col++) {
                const d = new Date(
                    gridStart.getFullYear(),
                    gridStart.getMonth(),
                    gridStart.getDate() + row * DAYS_IN_WEEK + col,
                );
                const iso = formatIso(d);
                const isStart = start !== null && iso === start;
                const isEnd = end !== null && iso === end;
                const isInRange =
                    start !== null
                    && end !== null
                    && iso > start
                    && iso < end;

                week.push({
                    iso,
                    day: d.getDate(),
                    inMonth: d.getMonth() === monthIdx,
                    disabled: disabledSet.value.has(iso),
                    isStart,
                    isEnd,
                    isInRange,
                    inCurrentWeek: highlightSet.value.has(iso),
                });
            }

            rows.push(week);

            const last = week[6]!.iso;
            const lastDate = new Date(`${last}T00:00:00`);

            if (lastDate.getMonth() !== monthIdx && row >= 4) {
                break;
            }
        }

        return rows;
    });

    const summary = computed<string>(() => {
        const start = range.value.startDate;
        const end = range.value.endDate;

        if (start === null) {
            return 'Aucune sélection';
        }

        if (ongoing.value) {
            return `Depuis le ${formatFr(start)} (en cours)`;
        }

        if (end === null) {
            return `Début : ${formatFr(start)}, sélectionnez la fin`;
        }

        return `Du ${formatFr(start)} au ${formatFr(end)}`;
    });

    function gotoPrevMonth(): void {
        if (currentMonth.value > 1) {
            currentMonth.value -= 1;

            return;
        }

        currentMonth.value = 12;
        currentYear.value -= 1;
    }

    function gotoNextMonth(): void {
        if (currentMonth.value < 12) {
            currentMonth.value += 1;

            return;
        }

        currentMonth.value = 1;
        currentYear.value += 1;
    }

    function setMonth(month: number): void {
        currentMonth.value = month;
    }

    function setYear(year: number): void {
        currentYear.value = year;
    }

    function jumpToIsoMonth(iso: string): void {
        const parts = iso.split('-').map(Number);
        const [y, m] = parts;

        if (
            y !== undefined
            && m !== undefined
            && Number.isFinite(y)
            && Number.isFinite(m)
        ) {
            currentYear.value = y;
            currentMonth.value = m;
        }
    }

    /**
     * Try to apply `[start, end]` to the model. On conflict with
     * `disabledDates`, set `errorMessage` and keep the range unchanged.
     */
    function tryApplyRange(start: string, end: string): boolean {
        const conflicts = rangeConflicts(start, end, disabledSet.value);

        if (conflicts.length > 0) {
            errorMessage.value = 'La plage choisie chevauche des dates déjà attribuées.';

            return false;
        }

        range.value = { startDate: start, endDate: end };
        errorMessage.value = null;

        return true;
    }

    function onDayClick(cell: DayCell): void {
        if (cell.disabled) {
            return;
        }

        const iso = cell.iso;

        // No startDate or complete range -> first click becomes new start.
        if (
            range.value.startDate === null
            || (range.value.startDate !== null && range.value.endDate !== null)
        ) {
            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;

            return;
        }

        // Ongoing mode: every click just re-anchors start.
        if (ongoing.value) {
            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;

            return;
        }

        // Second click in range mode: auto-normalize.
        const start = range.value.startDate;

        if (iso === start) {
            range.value = { startDate: start, endDate: start };
            errorMessage.value = null;

            return;
        }

        const [normStart, normEnd] = normalizeRange(start, iso);
        tryApplyRange(normStart, normEnd);
    }

    function onStartDateInput(iso: string): void {
        if (!isValidIsoDate(iso)) {
            return;
        }

        const currentEnd = range.value.endDate;

        // No endDate: just set startDate (verify iso is not in disabled).
        if (currentEnd === null) {
            if (disabledSet.value.has(iso)) {
                errorMessage.value = `Date refusée : ${formatFr(iso)} est déjà attribuée.`;

                return;
            }

            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;
            jumpToIsoMonth(iso);

            return;
        }

        // endDate present: auto-normalize if iso > endDate.
        const [normStart, normEnd] = normalizeRange(iso, currentEnd);
        const ok = tryApplyRange(normStart, normEnd);

        if (ok) {
            jumpToIsoMonth(normStart);
        }
    }

    function onEndDateInput(iso: string): void {
        if (!isValidIsoDate(iso)) {
            return;
        }

        const currentStart = range.value.startDate;

        // No startDate: end input acts as start (permissive UX).
        if (currentStart === null) {
            if (disabledSet.value.has(iso)) {
                errorMessage.value = `Date refusée : ${formatFr(iso)} est déjà attribuée.`;

                return;
            }

            range.value = { startDate: iso, endDate: null };
            errorMessage.value = null;
            jumpToIsoMonth(iso);

            return;
        }

        // Auto-normalize (swap if iso < currentStart).
        const [normStart, normEnd] = normalizeRange(currentStart, iso);
        const ok = tryApplyRange(normStart, normEnd);

        if (ok) {
            jumpToIsoMonth(normEnd);
        }
    }

    function clearSelection(): void {
        range.value = { startDate: null, endDate: null };
        errorMessage.value = null;
    }

    return {
        currentYear,
        currentMonth,
        errorMessage,
        monthLabel,
        monthOptions,
        yearOptions,
        weeks,
        summary,
        disabledSet,
        gotoPrevMonth,
        gotoNextMonth,
        setMonth,
        setYear,
        onDayClick,
        onStartDateInput,
        onEndDateInput,
        clearSelection,
    };
}
