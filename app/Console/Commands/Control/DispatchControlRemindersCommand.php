<?php

declare(strict_types=1);

namespace App\Console\Commands\Control;

use App\Services\Control\ReminderDispatchService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches regulatory control reminder emails (Chantier B / B3). Scheduled
 * daily (ADR-0008, timing in routes/console.php); idempotent and safe to run
 * manually. The `--date=` option drives the reference date for tests / backfill.
 */
final class DispatchControlRemindersCommand extends Command
{
    protected $signature = 'controls:dispatch-reminders {--date= : Date de référence ISO (défaut: aujourd\'hui)}';

    protected $description = 'Envoie les rappels email des échéances de contrôles réglementaires.';

    public function __construct(
        private readonly ReminderDispatchService $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $today = is_string($dateOption) && $dateOption !== ''
            ? CarbonImmutable::parse($dateOption)
            : CarbonImmutable::today();

        $summary = $this->dispatcher->dispatch($today);

        Log::info('controls:dispatch-reminders done', $summary->toArray());

        $this->info(sprintf(
            'Rappels: %d véhicules, %d rappels (%d emails), %d déjà envoyés, %d erreurs.',
            $summary->vehiclesScanned,
            $summary->remindersFired,
            $summary->emailsSent,
            $summary->skippedAlreadySent,
            $summary->errors,
        ));

        return self::SUCCESS;
    }
}
