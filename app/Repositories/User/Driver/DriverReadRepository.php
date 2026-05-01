<?php

declare(strict_types=1);

namespace App\Repositories\User\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Models\Contract;
use App\Models\Driver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implémentation Eloquent des lectures Driver — slim conforme ADR-0013.
 */
final class DriverReadRepository implements DriverReadRepositoryInterface
{
    public function findById(int $id): ?Driver
    {
        return Driver::query()->find($id);
    }

    public function findByIdWithRelations(int $id): ?Driver
    {
        return Driver::query()
            ->with(['companies' => function ($query): void {
                $query->orderByPivot('joined_at');
            }])
            ->withCount('contracts')
            ->find($id);
    }

    public function listAllForIndex(): Collection
    {
        return Driver::query()
            ->with(['companies' => function ($query): void {
                $query->whereNull('driver_company.left_at')
                    ->orderByPivot('joined_at');
            }])
            ->withCount('contracts')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function listForCompany(int $companyId, bool $includeInactive = true): Collection
    {
        $query = Driver::query()
            ->whereHas('companies', function ($q) use ($companyId, $includeInactive): void {
                $q->where('companies.id', $companyId);
                if (! $includeInactive) {
                    $q->whereNull('driver_company.left_at');
                }
            })
            ->with(['companies' => function ($q) use ($companyId): void {
                $q->where('companies.id', $companyId);
            }])
            ->orderBy('last_name')
            ->orderBy('first_name');

        return $query->get();
    }

    public function listActiveInCompanyDuring(
        int $companyId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return Driver::query()
            ->whereHas('companies', function ($q) use ($companyId, $startDate, $endDate): void {
                $q->where('companies.id', $companyId)
                    ->where('driver_company.joined_at', '<=', $startDate)
                    ->where(function ($inner) use ($endDate): void {
                        $inner->whereNull('driver_company.left_at')
                            ->orWhere('driver_company.left_at', '>=', $endDate);
                    });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function countFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): int {
        return Contract::query()
            ->where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->where('start_date', '>', $leftAt->toDateString())
            ->count();
    }

    public function listFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): Collection {
        return Contract::query()
            ->with(['vehicle:id,license_plate', 'company:id,short_code,legal_name,color'])
            ->where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->where('start_date', '>', $leftAt->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    public function countContractsForDriverGroupedByCompany(int $driverId): array
    {
        return Contract::query()
            ->where('driver_id', $driverId)
            ->selectRaw('company_id, COUNT(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }
}
