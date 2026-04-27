<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Dashboard;

use App\Fiscal\Resolver\FiscalYearResolver;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatsService;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly FiscalYearResolver $fiscalYear,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('User/Dashboard/Index/Index', [
            'stats' => $this->stats->computeStats($this->fiscalYear->resolve()),
        ]);
    }
}
