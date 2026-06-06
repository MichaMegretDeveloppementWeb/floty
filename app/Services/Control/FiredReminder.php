<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Enums\Control\ReminderKind;
use Carbon\CarbonImmutable;

/**
 * Result of {@see ControlReminderOccurrence::fires()}: which reminder kind fires
 * today and its CANONICAL scheduled date (Chantier B / B3).
 *
 * `scheduledOn` is the occurrence's canonical date (the first day of its
 * window), NOT the actual run date. The dispatcher journals it as `reminder_on`,
 * so a reminder caught up a day or two late (after a missed cron run) dedupes
 * against the same occurrence instead of re-sending.
 */
final readonly class FiredReminder
{
    public function __construct(
        public ReminderKind $kind,
        public CarbonImmutable $scheduledOn,
    ) {}
}
