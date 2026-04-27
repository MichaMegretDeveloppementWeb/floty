<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Data\User\Assignment\BulkCreateResultData;
use App\Data\User\Assignment\VehicleDatesData;
use App\DTO\Fiscal\AnnualCumulByPair;
use App\Models\Assignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Requêtes et opérations centrées sur le modèle Assignment.
 *
 * Centralise tous les accès Eloquent autour des attributions ; les
 * controllers ne touchent jamais directement à `Assignment::query()`.
 */
final class AssignmentQueryService
{
    /**
     * Cumul annuel des jours par couple (vehicle, company).
     *
     * Une seule requête SQL `GROUP BY` au lieu d'itérer toute la
     * table en PHP — gain massif sur grosses volumétries.
     */
    public function loadAnnualCumul(int $year): AnnualCumulByPair
    {
        $rows = Assignment::query()
            ->whereYear('date', $year)
            ->select('vehicle_id', 'company_id', DB::raw('COUNT(*) as days'))
            ->groupBy('vehicle_id', 'company_id')
            ->get();

        $byPair = [];
        foreach ($rows as $row) {
            $byPair[$row->vehicle_id.'|'.$row->company_id] = (int) $row->days;
        }

        return new AnnualCumulByPair($byPair);
    }

    /**
     * Densité hebdomadaire (vehicle_id × week_number → nb jours)
     * pour la heatmap planning.
     *
     * @return array<string, int> Clés "vehicleId|weekNumber" → jours
     */
    public function loadWeekDensity(int $year): array
    {
        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'date']);

        $density = [];
        foreach ($assignments as $a) {
            $week = (int) Carbon::parse($a->date)->isoWeek;
            $key = $a->vehicle_id.'|'.$week;
            $density[$key] = ($density[$key] ?? 0) + 1;
        }

        return $density;
    }

    /**
     * Dates occupées d'un véhicule (toutes entreprises) + map
     * companyId → liste de dates pour l'année courante.
     */
    public function vehicleDates(int $vehicleId, int $year): VehicleDatesData
    {
        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->get(['company_id', 'date']);

        $busy = [];
        $byCompany = [];
        foreach ($assignments as $a) {
            $iso = $a->date->toDateString();
            $busy[] = $iso;
            $byCompany[(string) $a->company_id] ??= [];
            $byCompany[(string) $a->company_id][] = $iso;
        }

        return new VehicleDatesData(
            vehicleBusyDates: array_values(array_unique($busy)),
            pairDates: $byCompany,
        );
    }

    /**
     * Création en masse d'attributions pour un couple sur N dates.
     * Les doublons (couple × date) sont silencieusement ignorés
     * via `INSERT IGNORE` (UNIQUE soft-delete par triggers).
     *
     * @param  list<string>  $dates  Format ISO Y-m-d
     */
    public function createBulk(int $vehicleId, int $companyId, array $dates): BulkCreateResultData
    {
        $now = now();
        $rows = array_map(
            static fn (string $date): array => [
                'vehicle_id' => $vehicleId,
                'company_id' => $companyId,
                'driver_id' => null,
                'date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $dates,
        );

        $inserted = DB::table('assignments')->insertOrIgnore($rows);
        $requested = count($dates);

        return new BulkCreateResultData(
            requested: $requested,
            inserted: $inserted,
            skipped: $requested - $inserted,
        );
    }
}
