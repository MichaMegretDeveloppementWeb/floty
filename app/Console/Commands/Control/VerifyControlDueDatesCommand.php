<?php

declare(strict_types=1);

namespace App\Console\Commands\Control;

use App\Services\Control\ControlDueDateRecomputeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Drift detector for the materialised `vehicles.controls_due_from` cache:
 * recomputes the value live and compares it to what is stored, without writing.
 * Any divergence is logged at warning level (the alert) so a missed write
 * channel can never stay silent. Scheduled daily right after the recompute;
 * also safe to run manually. The recompute command is what corrects drift.
 */
final class VerifyControlDueDatesCommand extends Command
{
    protected $signature = 'controls:verify-due-dates';

    protected $description = 'Détecte une dérive du cache controls_due_from (recalcul live vs stocké).';

    public function __construct(
        private readonly ControlDueDateRecomputeService $recompute,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $drift = $this->recompute->detectDrift();

        if ($drift === []) {
            $this->info('Cache controls_due_from cohérent (aucune dérive).');

            return self::SUCCESS;
        }

        Log::warning('controls:verify-due-dates: drift détectée sur le cache controls_due_from', [
            'count' => count($drift),
            'vehicles' => $drift,
        ]);

        $this->warn(sprintf(
            'Dérive détectée sur %d véhicule(s) : lancer controls:recompute-due-dates (détails dans les logs).',
            count($drift),
        ));

        return self::SUCCESS;
    }
}
