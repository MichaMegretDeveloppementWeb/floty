import { describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import {
    formatFr,
    formatIso,
    isValidIsoDate,
    normalizeRange,
    rangeConflicts,
    useDateRangePicker,
} from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
import type { DateRange } from '@/Composables/Ui/DateRangePicker/useDateRangePicker';

function setup(opts: {
    year?: number;
    startMonth?: number;
    disabledDates?: string[];
    initialRange?: DateRange;
    initialOngoing?: boolean;
} = {}) {
    const yearRef = ref<number>(opts.year ?? 2024);
    const startMonthRef = ref<number>(opts.startMonth ?? 1);
    const disabledRef = ref<readonly string[]>(opts.disabledDates ?? []);
    const range = ref<DateRange>(
        opts.initialRange ?? { startDate: null, endDate: null },
    );
    const ongoing = ref<boolean>(opts.initialOngoing ?? false);

    const ctx = useDateRangePicker(
        yearRef,
        startMonthRef,
        disabledRef,
        range,
        ongoing,
    );

    return { ctx, range, ongoing, yearRef };
}

function makeCell(iso: string, disabled = false) {
    return {
        iso,
        day: Number(iso.slice(-2)),
        inMonth: true,
        disabled,
        isStart: false,
        isEnd: false,
        isInRange: false,
    };
}

describe('formatIso', () => {
    it('pads month and day to 2 digits', () => {
        expect(formatIso(new Date(2024, 0, 5))).toBe('2024-01-05');
        expect(formatIso(new Date(2024, 11, 31))).toBe('2024-12-31');
    });
});

describe('formatFr', () => {
    it('reformats ISO to fr-FR DD/MM/YYYY', () => {
        expect(formatFr('2024-05-15')).toBe('15/05/2024');
    });
});

describe('isValidIsoDate', () => {
    it('accepts valid ISO dates', () => {
        expect(isValidIsoDate('2024-05-15')).toBe(true);
        expect(isValidIsoDate('2024-02-29')).toBe(true); // bissextile
    });

    it('rejects malformed strings', () => {
        expect(isValidIsoDate('2024-5-15')).toBe(false);
        expect(isValidIsoDate('2024/05/15')).toBe(false);
        expect(isValidIsoDate('15-05-2024')).toBe(false);
        expect(isValidIsoDate('')).toBe(false);
    });

    it('rejects impossible dates (round-trip différent)', () => {
        expect(isValidIsoDate('2024-02-30')).toBe(false);
        expect(isValidIsoDate('2024-13-01')).toBe(false);
        expect(isValidIsoDate('2023-02-29')).toBe(false); // non bissextile
    });
});

describe('normalizeRange', () => {
    it('returns [a, b] when a <= b', () => {
        expect(normalizeRange('2024-01-12', '2024-01-28')).toEqual([
            '2024-01-12',
            '2024-01-28',
        ]);
    });

    it('swaps when a > b', () => {
        expect(normalizeRange('2024-01-28', '2024-01-12')).toEqual([
            '2024-01-12',
            '2024-01-28',
        ]);
    });

    it('returns [a, a] when equal', () => {
        expect(normalizeRange('2024-01-15', '2024-01-15')).toEqual([
            '2024-01-15',
            '2024-01-15',
        ]);
    });
});

describe('rangeConflicts', () => {
    const disabled = new Set([
        '2024-05-10',
        '2024-05-15',
        '2024-05-20',
    ]);

    it('returns empty when no conflict', () => {
        expect(rangeConflicts('2024-05-01', '2024-05-09', disabled)).toEqual([]);
    });

    it('detects conflict at start boundary', () => {
        expect(rangeConflicts('2024-05-10', '2024-05-12', disabled)).toEqual([
            '2024-05-10',
        ]);
    });

    it('detects conflict at end boundary', () => {
        expect(rangeConflicts('2024-05-08', '2024-05-10', disabled)).toEqual([
            '2024-05-10',
        ]);
    });

    it('detects multiple conflicts', () => {
        expect(rangeConflicts('2024-05-09', '2024-05-21', disabled)).toEqual([
            '2024-05-10',
            '2024-05-15',
            '2024-05-20',
        ]);
    });

    it('handles single-day range', () => {
        expect(rangeConflicts('2024-05-15', '2024-05-15', disabled)).toEqual([
            '2024-05-15',
        ]);
        expect(rangeConflicts('2024-05-14', '2024-05-14', disabled)).toEqual([]);
    });
});

describe('useDateRangePicker — auto-normalize on click', () => {
    it('2nd click after start sets endDate normally', () => {
        const { ctx, range } = setup();
        ctx.onDayClick(makeCell('2024-01-12'));
        ctx.onDayClick(makeCell('2024-01-28'));
        expect(range.value).toEqual({
            startDate: '2024-01-12',
            endDate: '2024-01-28',
        });
    });

    it('2nd click before start swaps automatically', () => {
        const { ctx, range } = setup();
        ctx.onDayClick(makeCell('2024-01-28'));
        ctx.onDayClick(makeCell('2024-01-12'));
        expect(range.value).toEqual({
            startDate: '2024-01-12',
            endDate: '2024-01-28',
        });
    });

    it('2nd click on same day yields single-day range', () => {
        const { ctx, range } = setup();
        ctx.onDayClick(makeCell('2024-01-15'));
        ctx.onDayClick(makeCell('2024-01-15'));
        expect(range.value).toEqual({
            startDate: '2024-01-15',
            endDate: '2024-01-15',
        });
    });

    it('3rd click resets to a new start', () => {
        const { ctx, range } = setup();
        ctx.onDayClick(makeCell('2024-01-12'));
        ctx.onDayClick(makeCell('2024-01-28'));
        ctx.onDayClick(makeCell('2024-02-05'));
        expect(range.value).toEqual({
            startDate: '2024-02-05',
            endDate: null,
        });
    });

    it('disabled cell is ignored', () => {
        const { ctx, range } = setup();
        ctx.onDayClick(makeCell('2024-01-12', true));
        expect(range.value).toEqual({ startDate: null, endDate: null });
    });

    it('conflict on auto-normalized range posts errorMessage and keeps range untouched', () => {
        const { ctx, range } = setup({ disabledDates: ['2024-01-20'] });
        ctx.onDayClick(makeCell('2024-01-28'));
        ctx.onDayClick(makeCell('2024-01-12'));
        expect(range.value).toEqual({
            startDate: '2024-01-28',
            endDate: null,
        });
        expect(ctx.errorMessage.value).toContain('conflit');
    });
});

describe('useDateRangePicker — input date sync', () => {
    it('onStartDateInput sets startDate when no endDate', () => {
        const { ctx, range } = setup({ year: 2026, startMonth: 4 });
        ctx.onStartDateInput('2024-05-15');
        expect(range.value).toEqual({
            startDate: '2024-05-15',
            endDate: null,
        });
    });

    it('onStartDateInput jumps calendar to the input month', () => {
        const { ctx } = setup({ year: 2026, startMonth: 4 });
        ctx.onStartDateInput('2024-05-15');
        expect(ctx.currentYear.value).toBe(2024);
        expect(ctx.currentMonth.value).toBe(5);
    });

    it('onStartDateInput swaps when iso > existing endDate', () => {
        const { ctx, range } = setup({
            initialRange: { startDate: '2024-01-10', endDate: '2024-01-20' },
        });
        ctx.onStartDateInput('2024-01-25');
        expect(range.value).toEqual({
            startDate: '2024-01-20',
            endDate: '2024-01-25',
        });
    });

    it('onEndDateInput swaps when iso < existing startDate', () => {
        const { ctx, range } = setup({
            initialRange: { startDate: '2024-01-20', endDate: null },
        });
        ctx.onEndDateInput('2024-01-10');
        expect(range.value).toEqual({
            startDate: '2024-01-10',
            endDate: '2024-01-20',
        });
    });

    it('onEndDateInput posts errorMessage on conflict', () => {
        const { ctx, range } = setup({
            disabledDates: ['2024-01-15'],
            initialRange: { startDate: '2024-01-10', endDate: null },
        });
        ctx.onEndDateInput('2024-01-20');
        expect(range.value).toEqual({
            startDate: '2024-01-10',
            endDate: null,
        });
        expect(ctx.errorMessage.value).toContain('conflit');
    });

    it('invalid ISO is ignored silently', () => {
        const { ctx, range } = setup();
        ctx.onStartDateInput('not-a-date');
        ctx.onStartDateInput('');
        expect(range.value).toEqual({ startDate: null, endDate: null });
    });
});

describe('useDateRangePicker — month/year nav', () => {
    it('gotoNextMonth wraps year', () => {
        const { ctx } = setup({ year: 2024, startMonth: 12 });
        ctx.gotoNextMonth();
        expect(ctx.currentMonth.value).toBe(1);
        expect(ctx.currentYear.value).toBe(2025);
    });

    it('gotoPrevMonth wraps year', () => {
        const { ctx } = setup({ year: 2024, startMonth: 1 });
        ctx.gotoPrevMonth();
        expect(ctx.currentMonth.value).toBe(12);
        expect(ctx.currentYear.value).toBe(2023);
    });

    it('setMonth and setYear directly update', () => {
        const { ctx } = setup({ year: 2024, startMonth: 1 });
        ctx.setMonth(7);
        ctx.setYear(2026);
        expect(ctx.currentMonth.value).toBe(7);
        expect(ctx.currentYear.value).toBe(2026);
    });

    it('yearOptions returns ±5 years around props.year', () => {
        const { ctx } = setup({ year: 2024 });
        const years = ctx.yearOptions.value.map((o) => o.value);
        expect(years).toEqual([
            2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029,
        ]);
    });

    it('monthOptions returns 12 months capitalised', () => {
        const { ctx } = setup();
        const labels = ctx.monthOptions.value.map((o) => o.label);
        expect(labels.length).toBe(12);
        expect(labels[0]).toBe('Janvier');
        expect(labels[5]).toBe('Juin');
        expect(labels[11]).toBe('Décembre');
    });
});

describe('useDateRangePicker — ongoing mode', () => {
    it('activating ongoing clears endDate', async () => {
        const { ctx, range, ongoing } = setup({
            initialRange: { startDate: '2024-01-10', endDate: '2024-01-20' },
        });
        expect(ctx.errorMessage.value).toBeNull();
        ongoing.value = true;
        await nextTick();
        expect(range.value).toEqual({
            startDate: '2024-01-10',
            endDate: null,
        });
    });

    it('clicks in ongoing mode re-anchor start', () => {
        const { ctx, range } = setup({ initialOngoing: true });
        ctx.onDayClick(makeCell('2024-01-10'));
        ctx.onDayClick(makeCell('2024-01-25'));
        expect(range.value).toEqual({
            startDate: '2024-01-25',
            endDate: null,
        });
    });
});

describe('useDateRangePicker — clear', () => {
    it('clearSelection resets range and errorMessage', () => {
        const { ctx, range } = setup({
            initialRange: { startDate: '2024-01-10', endDate: '2024-01-20' },
        });
        ctx.errorMessage.value = 'previous error';
        ctx.clearSelection();
        expect(range.value).toEqual({ startDate: null, endDate: null });
        expect(ctx.errorMessage.value).toBeNull();
    });
});
