<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideReadRepositoryInterface;
use App\Data\User\Control\ControlRecipientData;
use App\Data\User\Control\Vehicle\EffectiveControlData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\RecipientOperation;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Support\Control\RecipientEmail;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves the effective control set for one vehicle (Chantier B / B2): the
 * active global catalog with the vehicle's sparse overrides applied (disabled
 * ones excluded) plus the vehicle-specific controls, each enriched with its
 * next échéance, schedule status, and the on-the-fly resolved recipient
 * cascade (settings -> definition -> vehicle). Read-only assembly; the actual
 * reminder SEND resolution + conductor injection is B3.
 */
final readonly class EffectiveControlResolver
{
    public function __construct(
        private ControlDefinitionReadRepositoryInterface $definitions,
        private VehicleControlOverrideReadRepositoryInterface $overrides,
        private ControlExecutionReadRepositoryInterface $executions,
        private ControlReminderSettingsReadRepositoryInterface $reminderSettings,
        private ControlScheduleService $schedule,
    ) {}

    /**
     * @return array<int, EffectiveControlData>
     */
    public function resolve(Vehicle $vehicle, CarbonImmutable $today): array
    {
        return $this->resolveWithContext($vehicle, $today, $this->buildContext());
    }

    /**
     * Builds the constant resolution context (settings, default recipients,
     * active catalog) ONCE, to reuse across vehicles in a fleet-wide scan and
     * avoid re-querying these per vehicle.
     */
    public function buildContext(): ControlResolutionContext
    {
        $settings = $this->reminderSettings->get();
        $defaultRecipients = $this->reminderSettings->defaultRecipients();

        return new ControlResolutionContext(
            settings: $settings,
            defaultRecipients: $defaultRecipients,
            baseRecipients: $this->baseRecipientMap($defaultRecipients),
            definitions: $this->definitions->listActiveForResolution(),
        );
    }

    /**
     * Resolves one vehicle against a pre-built context. Only per-vehicle data
     * (executions, overrides) is queried here.
     *
     * @return array<int, EffectiveControlData>
     */
    public function resolveWithContext(Vehicle $vehicle, CarbonImmutable $today, ControlResolutionContext $context): array
    {
        $lastExecutions = $this->executions->latestForVehicle($vehicle->id);
        $exitDate = $vehicle->exit_date?->toImmutable();

        $overridesByDefinition = [];
        $specifics = [];

        foreach ($this->overrides->findForVehicle($vehicle->id) as $override) {
            if ($override->control_definition_id !== null) {
                $overridesByDefinition[$override->control_definition_id] = $override;
            } else {
                $specifics[] = $override;
            }
        }

        $result = [];

        foreach ($context->definitions as $definition) {
            $override = $overridesByDefinition[$definition->id] ?? null;

            // Disabled controls stay in the set (greyed, reactivatable in the
            // UI); B3 simply skips them when sending reminders.
            $result[] = $this->buildFromGlobal($definition, $override, $vehicle, $today, $exitDate, $lastExecutions, $context->settings, $context->baseRecipients);
        }

        foreach ($specifics as $override) {
            $result[] = $this->buildFromSpecific($override, $vehicle, $today, $exitDate, $lastExecutions, $context->settings, $context->baseRecipients);
        }

        return $result;
    }

    /**
     * @param  array<string, CarbonImmutable>  $lastExecutions
     * @param  array<string, string>  $baseRecipients
     */
    private function buildFromGlobal(
        ControlDefinition $definition,
        ?VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        array $lastExecutions,
        ControlReminderSettings $settings,
        array $baseRecipients,
    ): EffectiveControlData {
        $anchor = $override?->anchor ?? $definition->anchor;
        $initialValue = $override?->initial_duration_value ?? $definition->initial_duration_value;
        $initialUnit = $override?->initial_duration_unit ?? $definition->initial_duration_unit;
        $cycleValue = $override?->cycle_value ?? $definition->cycle_value;
        $cycleUnit = $override?->cycle_unit ?? $definition->cycle_unit;

        $lastExecution = $lastExecutions['def:'.$definition->id] ?? null;
        $effectiveDaysBefore = $override?->reminder_days_before
            ?? $definition->reminder_days_before
            ?? $settings->days_before;
        $effectiveOnDueDay = $override?->reminder_on_due_day
            ?? $definition->reminder_on_due_day
            ?? $settings->remind_on_due_day;
        $effectiveRepeat = $override?->reminder_repeat_every_days
            ?? $definition->reminder_repeat_every_days
            ?? $settings->repeat_every_days;

        // Baseline the vehicle inherits if it does not customise its reminders:
        // the global control's own cycle if set, otherwise the general settings.
        $inheritedDaysBefore = $definition->reminder_days_before ?? $settings->days_before;
        $inheritedOnDueDay = $definition->reminder_on_due_day ?? $settings->remind_on_due_day;
        $inheritedRepeat = $definition->reminder_repeat_every_days ?? $settings->repeat_every_days;

        $nextDue = $this->schedule->nextDueDate(
            $this->anchorDate($vehicle, $anchor),
            $initialValue,
            $initialUnit,
            $cycleValue,
            $cycleUnit,
            $lastExecution,
        );

        // Level 1 (definition) deltas, then level 2 (vehicle override) deltas.
        $inheritedMap = $this->applyDeltas($baseRecipients, $definition->recipientDeltas);
        $effectiveMap = $override !== null
            ? $this->applyDeltas($inheritedMap, $override->recipientDeltas)
            : $inheritedMap;

        return new EffectiveControlData(
            definitionId: $definition->id,
            overrideId: $override?->id,
            isVehicleSpecific: false,
            name: $override?->name ?? $definition->name,
            anchor: $anchor,
            initialDurationValue: $initialValue,
            initialDurationUnit: $initialUnit,
            cycleValue: $cycleValue,
            cycleUnit: $cycleUnit,
            notifyDriver: $override?->notify_driver ?? $definition->notify_driver,
            impliesUnavailability: $override?->implies_unavailability ?? $definition->implies_unavailability,
            status: $override?->status ?? VehicleControlStatus::Active,
            isOverridden: $this->isFieldOverridden($override),
            customizeReminders: $override?->reminder_days_before !== null,
            reminderDaysBefore: $override?->reminder_days_before,
            reminderOnDueDay: $override?->reminder_on_due_day,
            reminderRepeatEveryDays: $override?->reminder_repeat_every_days,
            effectiveReminderDaysBefore: $effectiveDaysBefore,
            effectiveReminderOnDueDay: $effectiveOnDueDay,
            effectiveReminderRepeatEveryDays: $effectiveRepeat,
            inheritedReminderDaysBefore: $inheritedDaysBefore,
            inheritedReminderOnDueDay: $inheritedOnDueDay,
            inheritedReminderRepeatEveryDays: $inheritedRepeat,
            scheduleStatus: $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $effectiveDaysBefore, $exitDate),
            nextDueDate: $nextDue->toDateString(),
            lastExecutionDate: $lastExecution?->toDateString(),
            inheritedRecipients: $this->mapToRecipients($inheritedMap),
            ownRecipients: $this->overrideIncludes($override),
            excludedDefaultEmails: $this->overrideExcludes($override),
            effectiveRecipients: $this->mapToRecipients($effectiveMap),
        );
    }

    /**
     * @param  array<string, CarbonImmutable>  $lastExecutions
     * @param  array<string, string>  $baseRecipients
     */
    private function buildFromSpecific(
        VehicleControlOverride $override,
        Vehicle $vehicle,
        CarbonImmutable $today,
        ?CarbonImmutable $exitDate,
        array $lastExecutions,
        ControlReminderSettings $settings,
        array $baseRecipients,
    ): EffectiveControlData {
        // A vehicle-specific control carries a complete recipe (CHECK).
        $anchor = $override->anchor;
        $lastExecution = $lastExecutions['ovr:'.$override->id] ?? null;
        $effectiveDaysBefore = $override->reminder_days_before ?? $settings->days_before;
        $effectiveOnDueDay = $override->reminder_on_due_day ?? $settings->remind_on_due_day;
        $effectiveRepeat = $override->reminder_repeat_every_days ?? $settings->repeat_every_days;

        // A vehicle-specific control inherits the general settings directly.
        $inheritedDaysBefore = $settings->days_before;
        $inheritedOnDueDay = $settings->remind_on_due_day;
        $inheritedRepeat = $settings->repeat_every_days;

        $nextDue = $this->schedule->nextDueDate(
            $this->anchorDate($vehicle, $anchor),
            (int) $override->initial_duration_value,
            $override->initial_duration_unit,
            (int) $override->cycle_value,
            $override->cycle_unit,
            $lastExecution,
        );

        $effectiveMap = $this->applyDeltas($baseRecipients, $override->recipientDeltas);

        return new EffectiveControlData(
            definitionId: null,
            overrideId: $override->id,
            isVehicleSpecific: true,
            name: (string) $override->name,
            anchor: $anchor,
            initialDurationValue: (int) $override->initial_duration_value,
            initialDurationUnit: $override->initial_duration_unit,
            cycleValue: (int) $override->cycle_value,
            cycleUnit: $override->cycle_unit,
            notifyDriver: (bool) $override->notify_driver,
            impliesUnavailability: (bool) $override->implies_unavailability,
            status: $override->status,
            isOverridden: false,
            customizeReminders: $override->reminder_days_before !== null,
            reminderDaysBefore: $override->reminder_days_before,
            reminderOnDueDay: $override->reminder_on_due_day,
            reminderRepeatEveryDays: $override->reminder_repeat_every_days,
            effectiveReminderDaysBefore: $effectiveDaysBefore,
            effectiveReminderOnDueDay: $effectiveOnDueDay,
            effectiveReminderRepeatEveryDays: $effectiveRepeat,
            inheritedReminderDaysBefore: $inheritedDaysBefore,
            inheritedReminderOnDueDay: $inheritedOnDueDay,
            inheritedReminderRepeatEveryDays: $inheritedRepeat,
            scheduleStatus: $this->schedule->deriveStatus($nextDue, $lastExecution, $today, $effectiveDaysBefore, $exitDate),
            nextDueDate: $nextDue->toDateString(),
            lastExecutionDate: $lastExecution?->toDateString(),
            inheritedRecipients: $this->mapToRecipients($baseRecipients),
            ownRecipients: $this->overrideIncludes($override),
            excludedDefaultEmails: $this->overrideExcludes($override),
            effectiveRecipients: $this->mapToRecipients($effectiveMap),
        );
    }

    private function anchorDate(Vehicle $vehicle, ControlAnchor $anchor): CarbonImmutable
    {
        /** @var CarbonInterface $date */
        $date = $vehicle->getAttribute($anchor->vehicleColumn());

        return $date->toImmutable();
    }

    /**
     * @param  array<int, ControlRecipientData>  $level0Recipients
     * @return array<string, string>
     */
    private function baseRecipientMap(array $level0Recipients): array
    {
        $map = [];

        foreach ($level0Recipients as $recipient) {
            $map[RecipientEmail::normalize($recipient->email)] = $recipient->name;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $map  email => name
     * @param  iterable<ControlRecipientDelta>  $deltas
     * @return array<string, string>
     */
    private function applyDeltas(array $map, iterable $deltas): array
    {
        foreach ($deltas as $delta) {
            $email = RecipientEmail::normalize($delta->email);

            if ($delta->operation === RecipientOperation::Include) {
                $map[$email] = (string) $delta->name;
            } else {
                unset($map[$email]);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $map  email => name
     * @return array<int, ControlRecipientData>
     */
    private function mapToRecipients(array $map): array
    {
        $recipients = [];

        foreach ($map as $email => $name) {
            $recipients[] = new ControlRecipientData(name: (string) $name, email: (string) $email);
        }

        return $recipients;
    }

    /**
     * @return array<int, ControlRecipientData>
     */
    private function overrideIncludes(?VehicleControlOverride $override): array
    {
        if ($override === null) {
            return [];
        }

        return $override->recipientDeltas
            ->where('operation', RecipientOperation::Include)
            ->map(static fn ($delta): ControlRecipientData => new ControlRecipientData(
                name: (string) $delta->name,
                email: $delta->email,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function overrideExcludes(?VehicleControlOverride $override): array
    {
        if ($override === null) {
            return [];
        }

        return $override->recipientDeltas
            ->where('operation', RecipientOperation::Exclude)
            ->pluck('email')
            ->values()
            ->all();
    }

    /**
     * Robust "modified" detection: any overridable field set, or any level-2
     * recipient delta. With diff-based storage, a field is non-null only when it
     * genuinely differs from the global, so this is true iff the vehicle truly
     * customises the control.
     */
    private function isFieldOverridden(?VehicleControlOverride $override): bool
    {
        if ($override === null) {
            return false;
        }

        return $override->name !== null
            || $override->anchor !== null
            || $override->initial_duration_value !== null
            || $override->initial_duration_unit !== null
            || $override->cycle_value !== null
            || $override->cycle_unit !== null
            || $override->notify_driver !== null
            || $override->implies_unavailability !== null
            || $override->reminder_days_before !== null
            || $override->reminder_on_due_day !== null
            || $override->reminder_repeat_every_days !== null
            || $override->recipientDeltas->isNotEmpty();
    }
}
