<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Actions\Control\Reminder\SendControlReminderAction;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderLogReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderLogWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Control\ControlRecipientData;
use App\Data\User\Control\Vehicle\EffectiveControlData;
use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\VehicleControlStatus;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Support\Control\RecipientEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily control-reminder dispatch (Chantier B / B3). For each active vehicle it
 * resolves the effective controls, decides whether a reminder fires today, and
 * sends one email per recipient, journaling the occurrence for idempotence.
 * Per-control isolation: a failure on one control never aborts the run. No DB
 * transaction (mail is not rollback-able); the pre-check + UNIQUE journal handle
 * re-runs at occurrence granularity. Reminder SENDING honours status (paused /
 * disabled skipped) and exit_date (NotApplicable skipped); B1/B2 only stored
 * these states.
 */
final readonly class ReminderDispatchService
{
    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private EffectiveControlResolver $resolver,
        private ControlReminderOccurrence $occurrence,
        private ContractReadRepositoryInterface $contracts,
        private ControlReminderLogReadRepositoryInterface $logReader,
        private ControlReminderLogWriteRepositoryInterface $logWriter,
        private SendControlReminderAction $sender,
    ) {}

    public function dispatch(CarbonImmutable $today): ReminderRunSummary
    {
        $vehiclesScanned = 0;
        $controlsEvaluated = 0;
        $remindersFired = 0;
        $emailsSent = 0;
        $skippedAlreadySent = 0;
        $errors = 0;

        // Constant data (settings, default recipients, active catalog) is read
        // once and reused for every vehicle, not re-queried per vehicle.
        $context = $this->resolver->buildContext();

        foreach ($this->vehicles->findActiveForReminderScan($today) as $vehicle) {
            $vehiclesScanned++;
            $driversForVehicle = null; // lazily fetched at most once per vehicle

            foreach ($this->resolver->resolveWithContext($vehicle, $today, $context) as $control) {
                $controlsEvaluated++;

                if ($control->status !== VehicleControlStatus::Active) {
                    continue;
                }

                if ($control->scheduleStatus === ControlScheduleStatus::NotApplicable) {
                    continue;
                }

                $nextDue = CarbonImmutable::parse($control->nextDueDate);
                $kind = $this->occurrence->fires(
                    $nextDue,
                    $control->effectiveReminderDaysBefore,
                    $control->effectiveReminderOnDueDay,
                    $control->effectiveReminderRepeatEveryDays,
                    $today,
                );

                if ($kind === null) {
                    continue;
                }

                if ($this->logReader->existsForOccurrence(
                    $vehicle->id,
                    $control->definitionId,
                    $control->overrideId,
                    $nextDue->toDateString(),
                    $today->toDateString(),
                )) {
                    $skippedAlreadySent++;

                    continue;
                }

                if ($control->notifyDriver) {
                    $driversForVehicle ??= $this->contracts->driversForVehicleOnDate($vehicle->id, $today);
                }

                $recipients = $this->assembleRecipients($control, $driversForVehicle ?? []);

                if ($recipients === []) {
                    // No recipient yet: do not journal, so it retries when one appears.
                    continue;
                }

                try {
                    foreach ($recipients as $recipient) {
                        $this->sender->send($recipient, $control, $vehicle, $kind);
                        $emailsSent++;
                    }

                    $this->logWriter->record([
                        'vehicle_id' => $vehicle->id,
                        'control_definition_id' => $control->definitionId,
                        'vehicle_control_override_id' => $control->overrideId,
                        'due_on' => $nextDue->toDateString(),
                        'reminder_on' => $today->toDateString(),
                        'kind' => $kind->value,
                        'recipients_count' => count($recipients),
                        'sent_at' => CarbonImmutable::now(),
                    ]);

                    $remindersFired++;
                } catch (Throwable $e) {
                    Log::error('controls:dispatch-reminders control failed', [
                        'vehicle_id' => $vehicle->id,
                        'control_definition_id' => $control->definitionId,
                        'vehicle_control_override_id' => $control->overrideId,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        }

        return new ReminderRunSummary(
            vehiclesScanned: $vehiclesScanned,
            controlsEvaluated: $controlsEvaluated,
            remindersFired: $remindersFired,
            emailsSent: $emailsSent,
            skippedAlreadySent: $skippedAlreadySent,
            errors: $errors,
        );
    }

    /**
     * Effective recipients (settings/definition/vehicle cascade) plus the
     * vehicle's active driver(s) when the control notifies the driver, deduped
     * by normalized email (the configured recipients win on collision). The
     * driver list is fetched once per vehicle by the caller and passed in.
     *
     * @param  array<int, Driver>  $drivers
     * @return array<int, ControlRecipientData>
     */
    private function assembleRecipients(EffectiveControlData $control, array $drivers): array
    {
        $byEmail = [];

        foreach ($control->effectiveRecipients as $recipient) {
            $byEmail[RecipientEmail::normalize($recipient->email)] = $recipient;
        }

        if ($control->notifyDriver) {
            foreach ($drivers as $driver) {
                $email = $driver->email;

                if ($email === null || $email === '') {
                    continue;
                }

                $key = RecipientEmail::normalize($email);

                if (! isset($byEmail[$key])) {
                    $byEmail[$key] = new ControlRecipientData(name: $driver->full_name, email: $email);
                }
            }
        }

        return array_values($byEmail);
    }
}
