<?php

declare(strict_types=1);

namespace App\Console\Commands\Control;

use App\Services\Control\ControlDueDateRecomputeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fully recomputes the materialised `vehicles.controls_due_from` cache for the
 * whole in-fleet scope. Serves two purposes: the one-off backfill after the
 * column is added, and the nightly self-heal that corrects any drift the
 * write-time observers could have missed (bulk write, future channel, etc.).
 * Idempotent and safe to run manually.
 */
final class RecomputeControlDueDatesCommand extends Command
{
    protected $signature = 'controls:recompute-due-dates';

    protected $description = 'Recalcule le cache controls_due_from (échéances de contrôles) de toute la flotte.';

    public function __construct(
        private readonly ControlDueDateRecomputeService $recompute,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->recompute->forFleet();

        Log::info('controls:recompute-due-dates done');
        $this->info('Cache controls_due_from recalculé pour la flotte.');

        return self::SUCCESS;
    }
}
