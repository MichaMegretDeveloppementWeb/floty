<?php

namespace App\Http\Controllers\User\Planning;

use App\Data\User\Assignment\BulkCreateResultData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Data\User\Fiscal\FiscalPreviewData;
use App\Data\User\Planning\BulkCreateAssignmentsInputData;
use App\Data\User\Planning\PlanningHeatmapVehicleData;
use App\Data\User\Planning\PlanningWeekData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekCompanyPresenceData;
use App\Data\User\Planning\WeekDayAssignmentData;
use App\Data\User\Planning\WeekDaySlotData;
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
use Spatie\LaravelData\DataCollection;

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
        $year = (int) config('floty.fiscal.current_year');

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

            $vehiclesPayload[] = new PlanningHeatmapVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeks: $weeks,
                daysTotal: array_sum($weeks),
                annualTaxDue: round($totalDue, 2),
            );
        }

        $companiesPayload = $companies
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return Inertia::render('User/Planning/Index', [
            'vehicles' => PlanningHeatmapVehicleData::collect($vehiclesPayload, DataCollection::class),
            'companies' => CompanyOptionData::collect($companiesPayload, DataCollection::class),
        ]);
    }

    /**
     * Détail d'une semaine pour un véhicule donné (drawer).
     *
     * GET /app/planning/week?vehicleId=X&week=N
     */
    public function week(Request $request): JsonResponse
    {
        $year = (int) config('floty.fiscal.current_year');
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
            $days[] = new WeekDaySlotData(
                date: $iso,
                dayLabel: $cursor->translatedFormat('D d'),
                assignment: $assignment ? new WeekDayAssignmentData(
                    id: $assignment->id,
                    company: new CompanyOptionData(
                        id: $assignment->company->id,
                        shortCode: $assignment->company->short_code,
                        legalName: $assignment->company->legal_name,
                        color: $assignment->company->color,
                    ),
                ) : null,
            );
            $cursor->addDay();
        }

        // Agrégat des entreprises présentes sur cette semaine.
        $byCompany = $dayAssignments->groupBy('company_id')
            ->map(static fn ($group, $companyId): WeekCompanyPresenceData => new WeekCompanyPresenceData(
                company: new CompanyOptionData(
                    id: (int) $companyId,
                    shortCode: $group->first()->company->short_code,
                    legalName: $group->first()->company->legal_name,
                    color: $group->first()->company->color,
                ),
                days: $group->count(),
            ))
            ->values()
            ->all();

        $payload = new PlanningWeekData(
            weekNumber: $weekNumber,
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            vehicleId: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            days: $days,
            companiesOnWeek: $byCompany,
        );

        return response()->json($payload);
    }

    /**
     * Aperçu taxes induites en temps réel pour la création d'attribution
     * depuis le drawer.
     *
     * POST /app/planning/preview-taxes
     * Body: { vehicleId, companyId, dates: ["2024-01-05", "2024-01-06", ...] }
     */
    public function previewTaxes(PreviewTaxesInputData $input): JsonResponse
    {
        $year = (int) config('floty.fiscal.current_year');
        $yearPrefix = $year.'-';

        // Ne compter que les jours dans l'année fiscale courante.
        $newDates = array_values(array_filter(
            $input->dates,
            static fn (string $d) => str_starts_with($d, $yearPrefix),
        ));

        $alreadyAssignedForPair = Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $input->vehicleId)
            ->where('company_id', $input->companyId)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $newForPair = array_values(array_diff($newDates, $alreadyAssignedForPair));
        $newDaysCount = count($newForPair);

        $existingCumul = count($alreadyAssignedForPair);
        $futureCumul = $existingCumul + $newDaysCount;

        $vehicle = Vehicle::findOrFail($input->vehicleId);

        $before = $existingCumul > 0
            ? $this->calculator->calculate($vehicle, $existingCumul, $existingCumul, $year)
            : null;
        $after = $this->calculator->calculate($vehicle, $futureCumul, $futureCumul, $year);

        $incrementalDue = $after->totalDue - ($before?->totalDue ?? 0.0);

        $payload = new FiscalPreviewData(
            fiscalYear: $year,
            newDaysCount: $newDaysCount,
            existingCumul: $existingCumul,
            futureCumul: $futureCumul,
            before: $before !== null ? FiscalBreakdownData::from($before) : null,
            after: FiscalBreakdownData::from($after),
            incrementalDue: round($incrementalDue, 2),
        );

        return response()->json($payload);
    }

    /**
     * Création en masse d'attributions depuis le drawer.
     *
     * POST /app/planning/assignments
     * Body: { vehicleId, companyId, dates: ["2024-..."] }
     */
    public function storeBulk(BulkCreateAssignmentsInputData $input): JsonResponse
    {
        $rows = [];
        $now = now();
        foreach ($input->dates as $date) {
            $rows[] = [
                'vehicle_id' => $input->vehicleId,
                'company_id' => $input->companyId,
                'driver_id' => null,
                'date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = DB::table('assignments')->insertOrIgnore($rows);
        $requested = count($input->dates);

        $payload = new BulkCreateResultData(
            requested: $requested,
            inserted: $inserted,
            skipped: $requested - $inserted,
        );

        return response()->json($payload);
    }
}
