<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ReminderKind;
use Carbon\CarbonImmutable;

/**
 * Pure decision: does a control reminder fire on `today`, and of which kind
 * (Chantier B / B3)? No DB, no ambient clock (today injected), so it is a tiny
 * fully-testable unit. Eligibility (control active, échéance applicable) is the
 * caller's concern; this is purely the date arithmetic.
 *
 * Occurrence dates relative to the échéance `nextDue`:
 *   - before : nextDue - daysBefore ;
 *   - on_due : nextDue (when enabled) ;
 *   - after  : nextDue + k * repeatEveryDays (k >= 1), while not yet done.
 *
 * "Done" needs no special case: recording an execution advances nextDue into
 * the future, so `today > nextDue` becomes false and the after-repeats stop.
 * The only collision is `daysBefore = 0` (before-date == due-date); the order
 * below returns `Before` in that degenerate case.
 */
final class ControlReminderOccurrence
{
    public function fires(
        CarbonImmutable $nextDue,
        int $daysBefore,
        bool $onDueDay,
        int $repeatEveryDays,
        CarbonImmutable $today,
    ): ?ReminderKind {
        if ($today->equalTo($nextDue->subDays($daysBefore))) {
            return ReminderKind::Before;
        }

        if ($onDueDay && $today->equalTo($nextDue)) {
            return ReminderKind::OnDue;
        }

        if ($today->greaterThan($nextDue) && $repeatEveryDays >= 1) {
            $daysSince = (int) $nextDue->diffInDays($today);

            if ($daysSince % $repeatEveryDays === 0) {
                return ReminderKind::After;
            }
        }

        return null;
    }
}
