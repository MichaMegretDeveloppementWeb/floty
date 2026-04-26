<?php

namespace App\Http\Controllers\User\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\FiscalRule;
use App\Models\Vehicle;
use App\Services\Fiscal\FiscalCalculator;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    public function __invoke(): Response
    {
        // Année fiscale = source unique `config('floty.fiscal.current_year')`,
        // également exposée en shared props Inertia (`fiscal.currentYear`).
        $year = (int) config('floty.fiscal.current_year');

        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'company_id']);

        $cumulByPair = [];
        foreach ($assignments as $a) {
            $key = $a->vehicle_id.'|'.$a->company_id;
            $cumulByPair[$key] = ($cumulByPair[$key] ?? 0) + 1;
        }

        $totalDue = 0.0;
        $vehicleCache = [];
        foreach ($cumulByPair as $pairKey => $days) {
            [$vehicleId] = explode('|', (string) $pairKey);
            $vehicle = $vehicleCache[$vehicleId] ??= Vehicle::find($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $breakdown = $this->calculator->calculate($vehicle, $days, $days, $year);
            $totalDue += $breakdown->totalDue;
        }

        return Inertia::render('User/Dashboard/Index', [
            'stats' => [
                'vehiclesCount' => Vehicle::query()
                    ->whereNull('exit_date')
                    ->count(),
                'companiesCount' => Company::query()
                    ->where('is_active', true)
                    ->count(),
                'assignmentsYear' => $assignments->count(),
                'fiscalRulesCount' => FiscalRule::query()
                    ->where('fiscal_year', $year)
                    ->where('is_active', true)
                    ->count(),
                'totalTaxDue' => round($totalDue, 2),
            ],
        ]);
    }
}
