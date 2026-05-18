<?php

declare(strict_types=1);

namespace App\Repositories\User\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Models\RentalDiscount;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implémentation Eloquent du contrat de lecture des réductions
 * commerciales.
 *
 * Repository sans état · singleton via
 * {@see App\Providers\RepositoryServiceProvider}.
 */
final class RentalDiscountReadRepository implements RentalDiscountReadRepositoryInterface
{
    public function findById(int $id): ?RentalDiscount
    {
        return RentalDiscount::query()
            ->with('vehicles')
            ->find($id);
    }

    public function findByIdWithTrashed(int $id): ?RentalDiscount
    {
        return RentalDiscount::query()
            ->withTrashed()
            ->with('vehicles')
            ->find($id);
    }

    public function existsAny(): bool
    {
        return RentalDiscount::query()->exists();
    }

    public function findActiveForCompanyYear(int $companyId, int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return RentalDiscount::query()
            ->with('vehicles')
            ->forCompany($companyId)
            ->overlappingPeriod($yearStart, $yearEnd)
            ->orderBy('start_date')
            ->get();
    }

    public function findActiveForCompaniesYear(array $companyIds, int $year): Collection
    {
        if ($companyIds === []) {
            return RentalDiscount::query()->whereRaw('1 = 0')->get();
        }

        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        return RentalDiscount::query()
            ->with('vehicles')
            ->whereIn('company_id', $companyIds)
            ->overlappingPeriod($yearStart, $yearEnd)
            ->orderBy('company_id')
            ->orderBy('start_date')
            ->get();
    }

    public function findOverlappingForCompany(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): Collection {
        $query = RentalDiscount::query()
            ->with('vehicles')
            ->forCompany($companyId)
            ->overlappingPeriod($startDate, $endDate);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->orderBy('start_date')->get();
    }

    public function findActiveListingVehicleOn(int $vehicleId, string $date): Collection
    {
        // Une réduction « liste explicitement » un véhicule si la table
        // pivot contient l'association. Les réductions « tous véhicules »
        // (pivot vide) ne sont **pas** retournées · cf. doc-block de
        // l'interface (sémantique du check de suppression véhicule).
        return RentalDiscount::query()
            ->with('vehicles')
            ->activeOn($date)
            ->whereHas('vehicles', static function ($q) use ($vehicleId): void {
                $q->where('vehicles.id', $vehicleId);
            })
            ->orderBy('start_date')
            ->get();
    }
}
