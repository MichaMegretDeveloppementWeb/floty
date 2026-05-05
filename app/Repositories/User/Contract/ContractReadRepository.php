<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\Shared\Listing\SortDirection;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Implémentation Eloquent des lectures Contract - slim conforme
 * ADR-0013 (zéro transformation, zéro décision métier).
 *
 * La composition de DTOs vit dans
 * {@see ContractQueryService}.
 */
final class ContractReadRepository implements ContractReadRepositoryInterface
{
    public function findById(int $id): ?Contract
    {
        return Contract::query()->find($id);
    }

    public function findByIdWithRelations(int $id): ?Contract
    {
        return Contract::query()
            ->with([
                'vehicle.fiscalCharacteristics',
                'company',
                'driver',
            ])
            ->find($id);
    }

    public function findByVehicleAndYear(int $vehicleId, int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        // Plages contrats qui croisent l'année : start_date <= 31/12 ET end_date >= 01/01.
        return Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderBy('start_date')
            ->get();
    }

    public function findActiveForYear(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return Contract::query()
            ->where('start_date', '<=', $yearEnd)
            ->where('end_date', '>=', $yearStart)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get();
    }

    public function listForCompany(int $companyId): Collection
    {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                'company:id,short_code,legal_name,color',
                'driver:id,first_name,last_name',
            ])
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function listForVehicle(int $vehicleId): Collection
    {
        return Contract::query()
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                'company:id,short_code,legal_name,color',
                'driver:id,first_name,last_name',
            ])
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return Contract::query()
            ->with('company:id,short_code,legal_name,color')
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    public function findOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): ?Contract {
        $query = Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->first();
    }

    public function findAllOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
    ): Collection {
        return Contract::query()
            ->with('company:id,short_code,legal_name,color')
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('start_date')
            ->get();
    }

    public function existsAny(): bool
    {
        return Contract::query()->exists();
    }

    public function paginateForIndex(ContractIndexQueryData $query): LengthAwarePaginator
    {
        $direction = $query->sortDirection === SortDirection::Desc ? 'desc' : 'asc';

        $eloquentQuery = Contract::query()
            ->select('contracts.*')
            ->with([
                'vehicle:id,license_plate,exit_date,exit_reason',
                'company:id,short_code,legal_name,color',
                'driver:id,first_name,last_name',
            ]);

        // Filtres exact match.
        if ($query->vehicleId !== null) {
            $eloquentQuery->where('contracts.vehicle_id', $query->vehicleId);
        }
        if ($query->companyId !== null) {
            $eloquentQuery->where('contracts.company_id', $query->companyId);
        }
        if ($query->driverId !== null) {
            $eloquentQuery->where('contracts.driver_id', $query->driverId);
        }
        if ($query->type !== null) {
            $eloquentQuery->where('contracts.contract_type', $query->type);
        }

        // Filtre période : chevauchement [periodStart, periodEnd].
        // `effectivePeriod()` dérive l'exercice complet quand `year` est
        // présent (mode « Année » du toggle UI), sinon retourne les bornes
        // saisies tel quel (mode « Période personnalisée »).
        $period = $query->effectivePeriod();
        if ($period['periodStart'] !== null) {
            $eloquentQuery->where('contracts.end_date', '>=', $period['periodStart']);
        }
        if ($period['periodEnd'] !== null) {
            $eloquentQuery->where('contracts.start_date', '<=', $period['periodEnd']);
        }

        // Search combo : LIKE sur vehicle/company/driver via whereHas.
        if ($query->search !== null) {
            $term = '%'.$query->search.'%';
            $eloquentQuery->where(function (Builder $w) use ($term): void {
                $w->whereHas('vehicle', fn (Builder $qv) => $qv
                    ->where('license_plate', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model', 'like', $term))
                    ->orWhereHas('company', fn (Builder $qc) => $qc
                        ->where('short_code', 'like', $term)
                        ->orWhere('legal_name', 'like', $term))
                    ->orWhereHas('driver', fn (Builder $qd) => $qd
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term));
            });
        }

        // Tri whitelist (cf. ContractIndexQueryData::allowedSortKeys()).
        // `vehicle` et `company` utilisent un leftJoin temporaire pour
        // ordonner sur la colonne textuelle de la relation.
        match ($query->sortKey) {
            'vehicle' => $eloquentQuery
                ->leftJoin('vehicles', 'contracts.vehicle_id', '=', 'vehicles.id')
                ->orderBy('vehicles.license_plate', $direction),
            'company' => $eloquentQuery
                ->leftJoin('companies', 'contracts.company_id', '=', 'companies.id')
                ->orderBy('companies.short_code', $direction),
            'startDate' => $eloquentQuery->orderBy('contracts.start_date', $direction),
            'endDate' => $eloquentQuery->orderBy('contracts.end_date', $direction),
            'duration' => $eloquentQuery->orderByRaw(
                'DATEDIFF(contracts.end_date, contracts.start_date) '.($direction === 'desc' ? 'desc' : 'asc'),
            ),
            'type' => $eloquentQuery->orderBy('contracts.contract_type', $direction),
            // Défaut : tri historique start_date DESC.
            default => $eloquentQuery->orderByDesc('contracts.start_date'),
        };

        return $eloquentQuery->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    public function findAllInWindow(string $start, string $end): Collection
    {
        return Contract::query()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('vehicle_id')
            ->orderBy('start_date')
            ->get(['id', 'vehicle_id', 'start_date', 'end_date']);
    }

    public function countForDriverInCompany(int $driverId, int $companyId): int
    {
        return Contract::query()
            ->where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->count();
    }

    public function countForCompany(int $companyId): int
    {
        return Contract::query()
            ->where('company_id', $companyId)
            ->count();
    }

    public function statsForCompanyInPeriod(
        int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
    ): array {
        $query = Contract::query()->where('company_id', $companyId);

        if ($periodStart !== null) {
            $query->where('end_date', '>=', $periodStart);
        }

        if ($periodEnd !== null) {
            $query->where('start_date', '<=', $periodEnd);
        }

        // Intersection (clamp) start/end à la fenêtre filtrée pour le
        // cumul de jours. Sans fenêtre, on prend la durée brute du contrat.
        // Bindings paramétrés (défense en profondeur — le DTO valide déjà
        // `date_format:Y-m-d`).
        $clampedEndExpr = $periodEnd !== null ? 'LEAST(end_date, ?)' : 'end_date';
        $clampedStartExpr = $periodStart !== null ? 'GREATEST(start_date, ?)' : 'start_date';

        $totalDaysExpr = "COALESCE(SUM(DATEDIFF({$clampedEndExpr}, {$clampedStartExpr}) + 1), 0) AS total_days";
        $bindings = [];
        if ($periodEnd !== null) {
            $bindings[] = $periodEnd;
        }
        if ($periodStart !== null) {
            $bindings[] = $periodStart;
        }

        // `toBase()` pour passer en QueryBuilder : `first()` retourne
        // ?stdClass (et non ?Contract), ce qui permet l'accès aux colonnes
        // calculées via selectRaw sans déclaration sur le Model.
        $row = (clone $query)
            ->toBase()
            ->selectRaw($totalDaysExpr, $bindings)
            ->selectRaw("SUM(CASE WHEN contract_type = 'lcd' THEN 1 ELSE 0 END) AS lcd_count")
            ->selectRaw("SUM(CASE WHEN contract_type = 'lld' THEN 1 ELSE 0 END) AS lld_count")
            ->first();

        if ($row === null) {
            return ['totalDays' => 0, 'lcdCount' => 0, 'lldCount' => 0];
        }

        return [
            'totalDays' => (int) ($row->total_days ?? 0),
            'lcdCount' => (int) ($row->lcd_count ?? 0),
            'lldCount' => (int) ($row->lld_count ?? 0),
        ];
    }

    public function firstContractYearForCompany(int $companyId): ?int
    {
        $row = DB::table('contracts')
            ->where('company_id', $companyId)
            ->selectRaw('YEAR(MIN(start_date)) AS first_year')
            ->first();

        if ($row === null || $row->first_year === null) {
            return null;
        }

        return (int) $row->first_year;
    }

    public function findActiveYearsForCompany(int $companyId): array
    {
        $rows = Contract::query()
            ->where('company_id', $companyId)
            ->get(['start_date', 'end_date']);

        $years = [];
        foreach ($rows as $contract) {
            $startYear = (int) $contract->start_date->format('Y');
            $endYear = (int) $contract->end_date->format('Y');
            for ($y = $startYear; $y <= $endYear; $y++) {
                $years[$y] = true;
            }
        }

        $list = array_keys($years);
        sort($list);

        return $list;
    }
}
