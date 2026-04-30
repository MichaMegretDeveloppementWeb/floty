<?php

declare(strict_types=1);

namespace App\Repositories\User\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implémentation Eloquent des lectures Contract — slim conforme
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
                'vehicle:id,license_plate',
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
                'vehicle:id,license_plate',
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

    public function listAll(): Collection
    {
        return Contract::query()
            ->with(['vehicle:id,license_plate', 'company:id,short_code,legal_name,color'])
            ->orderByDesc('start_date')
            ->get();
    }
}
