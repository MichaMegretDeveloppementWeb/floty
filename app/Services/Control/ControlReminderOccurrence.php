<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ReminderKind;
use Carbon\CarbonImmutable;

/**
 * Pure decision: does a control reminder fire on `today`, of which kind, and for
 * which canonical scheduled date (Chantier B / B3)? No DB, no ambient clock
 * (today injected), so it is a tiny fully-testable unit. Eligibility (control
 * active, échéance applicable) is the caller's concern; this is purely the date
 * arithmetic. Returns the dedup key alongside the kind via {@see FiredReminder}.
 *
 * WINDOWED matching (not exact-day) so a missed cron run is caught up: each
 * occurrence owns a window, and `fires()` returns it for any day inside that
 * window. The canonical date returned is the window's first day, so the
 * dispatcher's idempotence journal sends it once and skips the rest of the
 * window. Windows tile `[nextDue - daysBefore, +inf)` with no gap or overlap:
 *   - before : [nextDue - daysBefore, nextDue) -> canonical nextDue - daysBefore
 *   - on_due : [nextDue, nextDue + repeat)      -> canonical nextDue (when enabled)
 *   - after k: [nextDue + k*repeat, +(k+1))     -> canonical nextDue + k*repeat
 *
 * "Done" needs no special case: recording an execution advances nextDue into the
 * future, so today falls before the window again and nothing fires.
 */
final class ControlReminderOccurrence
{
    public function fires(
        CarbonImmutable $nextDue,
        int $daysBefore,
        bool $onDueDay,
        int $repeatEveryDays,
        CarbonImmutable $today,
    ): ?FiredReminder {
        $today = $today->startOfDay();
        $nextDue = $nextDue->startOfDay();
        $beforeDate = $nextDue->subDays($daysBefore);

        // Too early: the before window has not opened yet.
        if ($today->lessThan($beforeDate)) {
            return null;
        }

        // Before window [beforeDate, nextDue): a missed day is caught up here,
        // keyed on the canonical beforeDate so it sends at most once.
        if ($today->lessThan($nextDue)) {
            return new FiredReminder(ReminderKind::Before, $beforeDate);
        }

        // From the échéance onward, windows of `repeatEveryDays` days:
        // window 0 = on-due, window k >= 1 = the k-th after-reminder.
        if ($repeatEveryDays < 1) {
            return $onDueDay
                ? new FiredReminder(ReminderKind::OnDue, $nextDue)
                : null;
        }

        $window = intdiv((int) $nextDue->diffInDays($today), $repeatEveryDays);

        if ($window === 0) {
            return $onDueDay
                ? new FiredReminder(ReminderKind::OnDue, $nextDue)
                : null;
        }

        return new FiredReminder(
            ReminderKind::After,
            $nextDue->addDays($window * $repeatEveryDays),
        );
    }
}
