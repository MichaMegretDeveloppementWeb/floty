/**
 * Derives period metrics for the "Progress" tile of the discount Show page.
 *
 * Returns a stable breakdown purely derived from start/end + today:
 * `bigValue` is the large figure (% if active, days otherwise),
 * `progressPercent` drives the bar, `timelineLeft/Center/Right` are the 3 legends below it.
 *
 * No IO, no external reactivity: pure testable helper.
 */
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

type Status = 'planned' | 'active' | 'expired';

type PeriodMetrics = {
    /** Business label above the big number. */
    label: string;
    /** Main figure: "52 %" if active, "12 j" if planned/expired. */
    bigValue: string;
    /** Short descriptive phrase below the big number. */
    meta: string;
    /** 0..100 for the progress bar. */
    progressPercent: number;
    /** 3 legends below the bar (left / center / right). */
    timelineLeft: string;
    timelineCenter: string;
    timelineRight: string;
};

function parseLocalDate(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y!, m! - 1, d!);
}

function diffDaysInclusive(fromISO: string, toISO: string): number {
    const from = parseLocalDate(fromISO);
    const to = parseLocalDate(toISO);
    const diffMs = to.getTime() - from.getTime();

    return Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
}

function todayISO(): string {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

function formatDateShortFr(iso: string): string {
    const [, m, d] = iso.split('-');

    return `${d}/${m}`;
}

export function useRentalDiscountPeriodMetrics(
    startDate: () => string,
    endDate: () => string,
    status: () => Status,
): ComputedRef<PeriodMetrics> {
    return computed<PeriodMetrics>(() => {
        const start = startDate();
        const end = endDate();
        const today = todayISO();
        const totalDays = diffDaysInclusive(start, end);
        const currentStatus = status();

        if (currentStatus === 'planned') {
            const daysUntilStart = diffDaysInclusive(today, start) - 1;

            return {
                label: 'Avancement',
                bigValue: '0 %',
                meta: `Commence dans ${daysUntilStart} jour${daysUntilStart > 1 ? 's' : ''} · durée totale ${totalDays} jour${totalDays > 1 ? 's' : ''}`,
                progressPercent: 0,
                timelineLeft: formatDateShortFr(start),
                timelineCenter: '',
                timelineRight: formatDateShortFr(end),
            };
        }

        if (currentStatus === 'expired') {
            const daysSinceEnd = diffDaysInclusive(end, today) - 1;

            return {
                label: 'Avancement',
                bigValue: '100 %',
                meta: `Terminée depuis ${daysSinceEnd} jour${daysSinceEnd > 1 ? 's' : ''} · durée totale ${totalDays} jour${totalDays > 1 ? 's' : ''}`,
                progressPercent: 100,
                timelineLeft: formatDateShortFr(start),
                timelineCenter: '',
                timelineRight: formatDateShortFr(end),
            };
        }

        const daysElapsed = diffDaysInclusive(start, today);
        const progress = Math.round((daysElapsed / totalDays) * 100);

        return {
            label: 'Avancement',
            bigValue: `${progress} %`,
            meta: `${daysElapsed} jour${daysElapsed > 1 ? 's' : ''} sur ${totalDays}`,
            progressPercent: progress,
            timelineLeft: formatDateShortFr(start),
            timelineCenter: "Aujourd'hui",
            timelineRight: formatDateShortFr(end),
        };
    });
}
