<?php

declare(strict_types=1);

namespace App\Repositories\User\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Models\Assignment;
use App\Services\Assignment\AssignmentQueryService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des lectures Assignment — pure récupération.
 *
 * Toute la composition de DTO et le calcul d'agrégats vit dans
 * {@see AssignmentQueryService}.
 */
final class AssignmentReadRepository implements AssignmentReadRepositoryInterface
{
    public function loadAnnualCumulRows(int $year): Collection
    {
        // R-2024-008 : les jours d'indisponibilité fiscale (fourrière)
        // sont **déduits** du numérateur du prorata. Un NOT EXISTS
        // filtre les `assignments` dont la date tombe dans une période
        // d'indisponibilité fiscale du même véhicule.
        return Assignment::query()
            ->whereYear('date', $year)
            ->whereNotExists(function ($query): void {
                $query
                    ->from('unavailabilities')
                    ->whereColumn('unavailabilities.vehicle_id', 'assignments.vehicle_id')
                    ->where('unavailabilities.has_fiscal_impact', true)
                    ->whereNull('unavailabilities.deleted_at')
                    ->whereColumn('assignments.date', '>=', 'unavailabilities.start_date')
                    ->where(function ($subquery): void {
                        $subquery
                            ->whereColumn('assignments.date', '<=', 'unavailabilities.end_date')
                            ->orWhereNull('unavailabilities.end_date');
                    });
            })
            ->select('vehicle_id', 'company_id', DB::raw('COUNT(*) as days'))
            ->groupBy('vehicle_id', 'company_id')
            ->get();
    }

    public function findAssignmentsForYear(int $year): Collection
    {
        return Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'date']);
    }

    public function findAssignmentsForVehicle(int $vehicleId, int $year): Collection
    {
        return Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->get(['company_id', 'date']);
    }

    public function findWeekAssignments(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return Assignment::query()
            ->with('company:id,short_code,legal_name,color')
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    public function findDatesForPair(int $vehicleId, int $companyId, int $year): Collection
    {
        return Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->where('company_id', $companyId)
            ->pluck('date');
    }

    public function countForYear(int $year): int
    {
        return Assignment::query()->whereYear('date', $year)->count();
    }
}
