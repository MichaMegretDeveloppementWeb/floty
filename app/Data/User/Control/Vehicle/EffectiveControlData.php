<?php

declare(strict_types=1);

namespace App\Data\User\Control\Vehicle;

use App\Data\User\Control\ControlRecipientData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\VehicleControlStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One effective control on a vehicle (Chantier B / B2), composed by
 * {@see App\Services\Control\EffectiveControlResolver} from the global catalog,
 * the vehicle's sparse overrides, the last execution and the reminder cascade.
 *
 * A control is identified by `definitionId` (global, possibly overridden) or by
 * `overrideId` alone (vehicle-specific). The reminder fields carry the
 * override's OWN values (null = inherit); recipients are split into the
 * inherited (settings + definition) list, the vehicle's own additions and its
 * exclusions, plus the fully resolved effective list.
 */
#[TypeScript]
final class EffectiveControlData extends Data
{
    /**
     * @param  array<int, ControlRecipientData>  $inheritedRecipients
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedDefaultEmails
     * @param  array<int, ControlRecipientData>  $effectiveRecipients
     */
    public function __construct(
        public ?int $definitionId,
        public ?int $overrideId,
        public bool $isVehicleSpecific,
        public string $name,
        public ControlAnchor $anchor,
        public int $initialDurationValue,
        public DurationUnit $initialDurationUnit,
        public int $cycleValue,
        public DurationUnit $cycleUnit,
        public bool $notifyDriver,
        public bool $impliesUnavailability,
        public VehicleControlStatus $status,
        public bool $isOverridden,
        public bool $customizeReminders,
        public ?int $reminderDaysBefore,
        public ?bool $reminderOnDueDay,
        public ?int $reminderRepeatEveryDays,
        public int $effectiveReminderDaysBefore,
        public bool $effectiveReminderOnDueDay,
        public int $effectiveReminderRepeatEveryDays,
        public int $inheritedReminderDaysBefore,
        public bool $inheritedReminderOnDueDay,
        public int $inheritedReminderRepeatEveryDays,
        public ControlScheduleStatus $scheduleStatus,
        public string $nextDueDate,
        public ?string $lastExecutionDate,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $inheritedRecipients,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $ownRecipients,
        public array $excludedDefaultEmails,
        #[DataCollectionOf(ControlRecipientData::class)]
        public array $effectiveRecipients,
    ) {}
}
