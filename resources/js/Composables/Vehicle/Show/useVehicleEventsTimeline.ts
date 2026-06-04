import { computed, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

/**
 * A single event's presence on one timeline day.
 *
 *   - `dayIndex` / `dayCount`: 1-based position within the event's span and
 *     its inclusive total (e.g. "jour 2/5"); `dayCount` is null for an
 *     ongoing event.
 *   - `isOngoing`: open-ended event (no end date), rendered once on its start
 *     day labelled "en cours" (never unfolded indefinitely).
 *   - `collapsed`: defensive guard for an abnormally long closed event
 *     (> MAX_UNFOLD_DAYS). Such an event is shown once on its start day with
 *     its full period instead of one dot per day, to keep the timeline usable.
 */
export type VehicleEventTimelineEntry = {
    event: VehicleEvent;
    dayIndex: number;
    dayCount: number | null;
    isOngoing: boolean;
    collapsed: boolean;
};

/** One calendar day of the timeline with every event present that day. */
export type VehicleEventTimelineDay = {
    /** ISO `YYYY-MM-DD`. */
    date: string;
    entries: VehicleEventTimelineEntry[];
};

/**
 * Inclusive ISO `[from, to]` clamp applied by an active year / period filter.
 * A `null` side is open-ended. Only days inside the window are emitted, while
 * `dayIndex` / `dayCount` stay relative to the full event.
 */
export type VehicleEventTimelineWindow = {
    from: string | null;
    to: string | null;
};

/**
 * Above this inclusive span (one leap year) a closed event is treated as a
 * data-entry anomaly: it is collapsed to a single entry instead of being
 * unfolded into hundreds of dots.
 */
const MAX_UNFOLD_DAYS = 366;

const MS_PER_DAY = 86_400_000;

/** UTC-midnight epoch of an ISO date, immune to DST shifts. */
function isoToUtcMs(iso: string): number {
    const parts = iso.split('-');

    return Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
}

/** ISO `YYYY-MM-DD` of a UTC-midnight epoch. */
function utcMsToIso(ms: number): string {
    const dt = new Date(ms);
    const y = dt.getUTCFullYear();
    const m = String(dt.getUTCMonth() + 1).padStart(2, '0');
    const d = String(dt.getUTCDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * Unfold vehicle events into one timeline day per calendar date, most recent
 * first. A closed N-day event appears on each of its N days ("jour X/N"); an
 * ongoing event appears once on its start day; several events the same day are
 * grouped under that day (sorted stably by id). Pure, to ease unit testing.
 *
 * When `window` is set (active year / period filter), only the days inside the
 * inclusive `[from, to]` window are emitted, so an event spilling outside the
 * window shows only its in-window days. `dayIndex` / `dayCount` stay relative
 * to the full event (e.g. day 9/29). An ongoing or collapsed single-entry event
 * that started before the window is surfaced on the window start so it stays
 * visible for the filtered period.
 */
export function buildVehicleEventsTimeline(
    events: ReadonlyArray<VehicleEvent>,
    window: VehicleEventTimelineWindow | null = null,
): VehicleEventTimelineDay[] {
    const from = window?.from ?? null;
    const to = window?.to ?? null;

    const inWindow = (iso: string): boolean =>
        (from === null || iso >= from) && (to === null || iso <= to);

    /** Clamp a single-entry event's day to the window start when it began earlier. */
    const clampToWindowStart = (iso: string): string =>
        from !== null && iso < from ? from : iso;

    const byDate = new Map<string, VehicleEventTimelineEntry[]>();

    const push = (date: string, entry: VehicleEventTimelineEntry): void => {
        const bucket = byDate.get(date);

        if (bucket) {
            bucket.push(entry);
        } else {
            byDate.set(date, [entry]);
        }
    };

    for (const event of events) {
        if (event.endDate === null) {
            const day = clampToWindowStart(event.startDate);

            if (inWindow(day)) {
                push(day, {
                    event,
                    dayIndex: 1,
                    dayCount: null,
                    isOngoing: true,
                    collapsed: false,
                });
            }

            continue;
        }

        const startMs = isoToUtcMs(event.startDate);
        const endMs = isoToUtcMs(event.endDate);
        const dayCount = Math.round((endMs - startMs) / MS_PER_DAY) + 1;

        if (dayCount > MAX_UNFOLD_DAYS) {
            // Anomaly guard: a closed event spanning more than a year is shown
            // once on its start day (its full period is rendered via the
            // `collapsed` flag) instead of unfolding into hundreds of dots.
            const day = clampToWindowStart(event.startDate);

            if (inWindow(day)) {
                push(day, {
                    event,
                    dayIndex: 1,
                    dayCount,
                    isOngoing: false,
                    collapsed: true,
                });
            }

            continue;
        }

        for (let i = 0; i < dayCount; i++) {
            const iso = utcMsToIso(startMs + i * MS_PER_DAY);

            if (!inWindow(iso)) {
                continue;
            }

            push(iso, {
                event,
                dayIndex: i + 1,
                dayCount,
                isOngoing: false,
                collapsed: false,
            });
        }
    }

    return [...byDate.entries()]
        // Most recent day first.
        .sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0))
        .map(([date, entries]) => ({
            date,
            // Stable order within a day: oldest event id first.
            entries: [...entries].sort((a, b) => a.event.id - b.event.id),
        }));
}

/**
 * Per-day span label of a timeline entry:
 *   - ongoing event → "en cours";
 *   - abnormally long collapsed event → "du X au Y · N jours";
 *   - multi-day event → "jour X/N";
 *   - single-day event → "" (no label needed).
 */
export function formatVehicleEventDaySpan(entry: VehicleEventTimelineEntry): string {
    if (entry.isOngoing) {
        return 'en cours';
    }

    if (entry.collapsed) {
        const end = entry.event.endDate ?? entry.event.startDate;

        return `du ${formatDateFr(entry.event.startDate)} au ${formatDateFr(end)} · ${entry.dayCount} jours`;
    }

    if (entry.dayCount !== null && entry.dayCount > 1) {
        return `jour ${entry.dayIndex}/${entry.dayCount}`;
    }

    return '';
}

/**
 * Reactive wrapper around {@link buildVehicleEventsTimeline} for the
 * "Événements" tab timeline. `window` clamps the displayed days to the active
 * year / period filter (null = no clamp, full history).
 */
export function useVehicleEventsTimeline(
    events: MaybeRefOrGetter<ReadonlyArray<VehicleEvent>>,
    window: MaybeRefOrGetter<VehicleEventTimelineWindow | null> = null,
): ComputedRef<VehicleEventTimelineDay[]> {
    return computed(() => buildVehicleEventsTimeline(toValue(events), toValue(window)));
}
