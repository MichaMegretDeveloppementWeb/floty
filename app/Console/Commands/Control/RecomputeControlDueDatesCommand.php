<?php

declare(strict_types=1);

namespace App\Console\Commands\Control;

use App\Services\Control\ControlDueDateRecomputeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recomputes the `controls_due_from` cache for the whole fleet (backfill and
 * nightly self-heal). Idempotent.
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
