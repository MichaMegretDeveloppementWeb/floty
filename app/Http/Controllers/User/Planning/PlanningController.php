<?php

namespace App\Http\Controllers\User\Planning;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning — vue d'ensemble heatmap annuelle (CDC § 3.3).
 *
 * MVP limité à l'année 2024 (seule année pour laquelle les règles fiscales
 * ont été codées — cf. `taxes-rules/2024.md`). Affiche la matrice
 * véhicules × 52 semaines avec une densité colorée calée sur l'échelle
 * blue-50 → blue-950 du design system (0 → 7 jours utilisés).
 */
final class PlanningController extends Controller
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    public function index(Request $request): Response
    {
        $year = 2024; // MVP — figé pour la démo

        $vehicles = Vehicle::query()
            ->with([
                'fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to'),
            ])
            ->whereNull('deleted_at')
            ->orderBy('license_plate')
            ->get();

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('short_code')
            ->get(['id', 'short_code', 'legal_name', 'color']);

        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->get(['id', 'vehicle_id', 'company_id', 'date']);

        // Pré-agrégats : densité hebdo (vehicle × week) + cumul annuel couple
        // (vehicle × company) nécessaire à l'évaluation LCD.
        $densityByVehicleByWeek = [];
        $dailyAssignments = [];
        $cumulativeByPair = [];

        foreach ($assignments as $a) {
            $date = Carbon::parse($a->date);
            $week = (int) $date->isoWeek; // 1..52/53
            $key = $a->vehicle_id.'|'.$week;
            $densityByVehicleByWeek[$key] = ($densityByVehicleByWeek[$key] ?? 0) + 1;

            $dailyKey = $a->vehicle_id.'|'.$date->toDateString();
            $dailyAssignments[$dailyKey] = [
                'companyId' => $a->company_id,
                'assignmentId' => $a->id,
            ];

            $pairKey = $a->vehicle_id.'|'.$a->company_id;
            $cumulativeByPair[$pairKey] = ($cumulativeByPair[$pairKey] ?? 0) + 1;
        }

        // Payload véhicule — inclut le résumé fiscal annuel agrégé.
        $vehiclesPayload = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= 52; $w++) {
                $weeks[] = $densityByVehicleByWeek[$vehicle->id.'|'.$w] ?? 0;
            }

            // Calcul du total fiscal annuel pour ce véhicule (somme par entreprise).
            $totalDue = 0.0;
            foreach ($companies as $c) {
                $pairKey = $vehicle->id.'|'.$c->id;
                $days = $cumulativeByPair[$pairKey] ?? 0;
                if ($days === 0) {
                    continue;
                }
                $breakdown = $this->calculator->calculate($vehicle, $days, $days, $year);
                $totalDue += $breakdown->totalDue;
            }

            $vehiclesPayload[] = [
                'id' => $vehicle->id,
                'licensePlate' => $vehicle->license_plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'userType' => $fiscal->vehicle_user_type->value, // VP / VU
                'energy' => $fiscal->energy_source->value,
                'co2Method' => $fiscal->homologation_method->value,
                'co2Value' => $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                'taxableHorsepower' => $fiscal->taxable_horsepower,
                'weeks' => $weeks,
                'daysTotal' => array_sum($weeks),
                'annualTaxDue' => round($totalDue, 2),
            ];
        }

        return Inertia::render('User/Planning/Index', [
            'fiscalYear' => $year,
            'vehicles' => $vehiclesPayload,
            'companies' => $companies->map(fn ($c) => [
                'id' => $c->id,
                'shortCode' => $c->short_code,
                'legalName' => $c->legal_name,
                'color' => $c->color->value,
            ])->values(),
        ]);
    }

    /**
     * Détail d'une semaine pour un véhicule donné (drawer).
     *
     * GET /app/planning/week?vehicleId=X&week=N
     */
    public function week(Request $request): JsonResponse
    {
        $year = 2024;
        $vehicleId = (int) $request->query('vehicleId');
        $weekNumber = (int) $request->query('week');

        if ($vehicleId <= 0 || $weekNumber < 1 || $weekNumber > 53) {
            abort(400, 'Paramètres vehicleId et week requis.');
        }

        $vehicle = Vehicle::with([
            'fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to'),
        ])->findOrFail($vehicleId);

        $start = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $dayAssignments = Assignment::query()
            ->with('company:id,short_code,legal_name,color')
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->toDateString());

        // Pour chaque jour de la semaine, construire le slot.
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $iso = $cursor->toDateString();
            $assignment = $dayAssignments->get($iso);
            $days[] = [
                'date' => $iso,
                'dayLabel' => $cursor->translatedFormat('D d'),
                'assignment' => $assignment ? [
                    'id' => $assignment->id,
                    'company' => [
                        'id' => $assignment->company->id,
                        'shortCode' => $assignment->company->short_code,
                        'legalName' => $assignment->company->legal_name,
                        'color' => $assignment->company->color->value,
                    ],
                ] : null,
            ];
            $cursor->addDay();
        }

        // Agrégat des entreprises présentes sur cette semaine.
        $byCompany = $dayAssignments->groupBy('company_id')
            ->map(fn ($group, $companyId) => [
                'company' => [
                    'id' => (int) $companyId,
                    'shortCode' => $group->first()->company->short_code,
                    'legalName' => $group->first()->company->legal_name,
                    'color' => $group->first()->company->color->value,
                ],
                'days' => $group->count(),
            ])->values();

        return response()->json([
            'weekNumber' => $weekNumber,
            'weekStart' => $start->toDateString(),
            'weekEnd' => $end->toDateString(),
            'vehicleId' => $vehicle->id,
            'licensePlate' => $vehicle->license_plate,
            'days' => $days,
            'companiesOnWeek' => $byCompany,
        ]);
    }

    /**
     * Aperçu taxes induites en temps réel pour la création d'attribution
     * depuis le drawer.
     *
     * POST /app/planning/preview-taxes
     * Body: { vehicleId, companyId, dates: ["2024-01-05", "2024-01-06", ...] }
     */
    public function previewTaxes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicleId' => 'required|integer|exists:vehicles,id',
            'companyId' => 'required|integer|exists:companies,id',
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date_format:Y-m-d',
        ]);

        $year = 2024;

        // Ne compter que les jours en 2024 (MVP).
        $newDates = array_values(array_filter(
            $data['dates'],
            fn (string $d) => str_starts_with($d, '2024-'),
        ));

        // Dates déjà occupées par ce véhicule (sauf celles qu'on propose) —
        // filtrage pour ne pas double-compter si la date existe déjà pour
        // cette entreprise.
        $alreadyAssignedForPair = Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $data['vehicleId'])
            ->where('company_id', $data['companyId'])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $newForPair = array_values(array_diff($newDates, $alreadyAssignedForPair));
        $newDaysCount = count($newForPair);

        $existingCumul = count($alreadyAssignedForPair);
        $futureCumul = $existingCumul + $newDaysCount;

        $vehicle = Vehicle::findOrFail($data['vehicleId']);

        // Cumul AVANT la nouvelle attribution.
        $before = $existingCumul > 0
            ? $this->calculator->calculate($vehicle, $existingCumul, $existingCumul, $year)
            : null;

        // Cumul APRÈS (ce qui sera dû au total par ce couple).
        $after = $this->calculator->calculate($vehicle, $futureCumul, $futureCumul, $year);

        $incrementalDue = $after->totalDue - ($before?->totalDue ?? 0.0);

        return response()->json([
            'fiscalYear' => $year,
            'newDaysCount' => $newDaysCount,
            'existingCumul' => $existingCumul,
            'futureCumul' => $futureCumul,
            'before' => $before?->toArray(),
            'after' => $after->toArray(),
            'incrementalDue' => round($incrementalDue, 2),
        ]);
    }

    /**
     * Création en masse d'attributions depuis le drawer.
     *
     * POST /app/planning/assignments
     * Body: { vehicleId, companyId, dates: ["2024-..."] }
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicleId' => 'required|integer|exists:vehicles,id',
            'companyId' => 'required|integer|exists:companies,id',
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date_format:Y-m-d',
        ]);

        $rows = [];
        $now = now();
        foreach ($data['dates'] as $date) {
            $rows[] = [
                'vehicle_id' => $data['vehicleId'],
                'company_id' => $data['companyId'],
                'driver_id' => null,
                'date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = DB::table('assignments')->insertOrIgnore($rows);

        return response()->json([
            'requested' => count($data['dates']),
            'inserted' => $inserted,
            'skipped' => count($data['dates']) - $inserted,
        ]);
    }
}
