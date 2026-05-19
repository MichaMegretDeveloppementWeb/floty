<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Vehicle\PaginatedVehicleListData;
use App\Data\User\Vehicle\VehicleFilterOptionData;
use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Vehicle;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Spatie\LaravelData\DataCollection;

/**
 * Listings and enum helpers for the Vehicle domain, extracted from
 * `VehicleQueryService` for SRP.
 *
 *   - `listPaginatedSlim` · server-side Index, no fiscal/rental
 *     computation (initial payload, costs follow via `Inertia::defer`).
 *   - `costsForVehicleIds` · per-vehicle cost map (fiscal + rental)
 *     for the second wave, with batched VFC prewarm.
 *   - `listForLightSelector` · slim list (id, label, isExited) for
 *     every UI selector (filter dropdown, Create/Edit Contract form).
 *   - `fullYearTaxForVehicle` · on-demand full-year tax for the AJAX
 *     endpoint of the Create/Edit Contract form.
 *   - `firstRegistrationYearBounds` · year bounds for the Index filter.
 */
final class VehicleListingService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalYearContext $yearContext,
        private readonly RentalPriceCalculator $rentalPrice,
    ) {}

    /**
     * Slim Index variant · paginated list WITHOUT costs
     * (`fullYearTax` / `dailyTaxRate` / `rentalPriceFullYear` stay
     * `null`); no fiscal pipeline, no rental batch.
     *
     * The initial Inertia payload is served immediately (raw SQL
     * columns · plate, brand/model, status, first registration date).
     * The three computed columns arrive in a second `Inertia::defer`
     * round-trip from {@see VehicleController::index}, which calls
     * {@see costsForVehicleIds()}. Skeletons cover the cells while the
     * defer is in flight.
     *
     * ADR-0020 D6 · sorting by `fullYearTax` is intentionally absent
     * from the `sortKey` whitelist (computed value, not SQL).
     */
    public function listPaginatedSlim(VehicleIndexQueryData $query): PaginatedVehicleListData
    {
        $paginator = $this->vehicles->paginateForIndex($query);
        /** @var list<Vehicle> $vehicles */
        $vehicles = $paginator->items();

        $items = array_map(
            static fn (Vehicle $v): VehicleListItemData => new VehicleListItemData(
                id: $v->id,
                licensePlate: $v->license_plate,
                brand: $v->brand,
                model: $v->model,
                currentStatus: $v->current_status,
                firstFrenchRegistrationDate: $v->first_french_registration_date->format('Y-m-d'),
                acquisitionDate: $v->acquisition_date->format('Y-m-d'),
                exitDate: $v->exit_date?->format('Y-m-d'),
                exitReason: $v->exit_reason,
                isExited: $v->is_exited,
                fullYearTax: null,
                dailyTaxRate: null,
                rentalPriceFullYear: null,
            ),
            $vehicles,
        );

        return new PaginatedVehicleListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Per-vehicle costs map (full-year tax + rental) for the target
     * year. Used via `Inertia::defer` from
     * {@see VehicleController::index} to fill the three computed cells
     * after the initial slim render.
     *
     *   - `prewarmFullYearForVehicles` · single batch SQL pre-loading
     *     VFC segments + pipeline results · drops the per-vehicle VFC
     *     query (N vehicles · N queries · -95 % SQL).
     *   - `rentalPrice->forVehiclesAndYear` · 2 batched SQL queries
     *     (vs 12 × N).
     *
     * Strict equivalence guaranteed against the previous
     * `listPaginated` · `vehicleFullYearTax($v, $year)` returns the
     * same value as without prewarm (covered by
     * `FleetFiscalAggregatorTest::prewarm_equivalent_aux_appels_individuels`).
     *
     * Tolerates a year outside the registry · returns `0 €` rather
     * than crashing.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, array{fullYearTax: float, dailyTaxRate: float, rentalPriceFullYear: float|null}>
     */
    public function costsForVehicleIds(array $vehicleIds, int $year): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $vehicles = $this->vehicles->findByIdsIndexed($vehicleIds);
        $daysInYear = $this->yearContext->daysInYear($year);

        // Batch prewarm of VFC + pipeline results · removes the N+1
        // VFC query inside the `vehicleFullYearTax` loop below.
        $this->aggregator->prewarmFullYearForVehicles($vehicles, $year);

        // Batched rental prices · 2 SQL for the whole page instead of
        // 12 × N.
        $rentalPricesByVehicle = $this->rentalPrice->forVehiclesAndYear($vehicleIds, $year);

        $result = [];
        foreach ($vehicles as $v) {
            try {
                $fullYearTax = $this->aggregator->vehicleFullYearTax($v, $year);
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
            }

            $rentalCents = $rentalPricesByVehicle[$v->id] ?? null;

            $result[$v->id] = [
                'fullYearTax' => $fullYearTax,
                'dailyTaxRate' => round($fullYearTax / $daysInYear, 2, PHP_ROUND_HALF_UP),
                'rentalPriceFullYear' => $rentalCents === null ? null : $rentalCents / 100,
            ];
        }

        return $result;
    }

    /**
     * Slim list for UI selectors · filter dropdown on the Index,
     * vehicle selector of the Create/Edit Contract form, planning
     * picker, etc. No fiscal computation, single SQL on 6 columns.
     *
     * The vehicle's full-year tax (formerly eagerly computed in
     * `listForOptions`) is now on-demand via
     * `GET /app/vehicles/{vehicle}/full-year-tax`, triggered by the
     * frontend when the user actually picks a vehicle
     * (`useVehicleFullYearTax`). Includes exited vehicles
     * (ADR-0018 § 4 · keep consultation and retroactive edit of past
     * contracts accessible).
     *
     * @return DataCollection<int, VehicleFilterOptionData>
     */
    public function listForLightSelector(): DataCollection
    {
        $rows = $this->vehicles->findAllForOptions()
            ->map(static fn (Vehicle $v): VehicleFilterOptionData => new VehicleFilterOptionData(
                id: $v->id,
                licensePlate: $v->license_plate,
                label: sprintf('%s - %s %s', $v->license_plate, $v->brand, $v->model),
                isExited: $v->is_exited,
                exitDate: $v->exit_date?->format('Y-m-d'),
            ))
            ->values()
            ->all();

        return VehicleFilterOptionData::collect($rows, DataCollection::class);
    }

    /**
     * On-demand full-year tax for a vehicle, for the AJAX endpoint
     * called by `useVehicleFullYearTax` when the user picks a vehicle
     * in the Create/Edit Contract form.
     *
     * When `$targetYear` is outside the `AvailableYearsResolver`
     * scope, falls back to the most recent available year. Returns
     * `null` when no in-scope year is computable.
     *
     * @return array{cents: int, year: int, fallback: bool}|null
     */
    public function fullYearTaxForVehicle(Vehicle $vehicle, int $targetYear): ?array
    {
        try {
            $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $targetYear);

            return [
                'cents' => (int) round($breakdown->total * 100),
                'year' => $targetYear,
                'fallback' => false,
            ];
        } catch (FiscalCalculationException) {
            // Fallback below.
        }

        $availableYears = $this->availableYears->availableYears();
        rsort($availableYears);
        foreach ($availableYears as $candidateYear) {
            if ($candidateYear === $targetYear) {
                continue;
            }
            try {
                $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $candidateYear);

                return [
                    'cents' => (int) round($breakdown->total * 100),
                    'year' => $candidateYear,
                    'fallback' => true,
                ];
            } catch (FiscalCalculationException) {
                continue;
            }
        }

        return null;
    }

    /**
     * Min/max first-registration year across the fleet. Feeds the
     * year-range filter on the Index. Returns `null` when the fleet
     * is empty (the frontend hides the filter).
     *
     * @return array{min: int, max: int}|null
     */
    public function firstRegistrationYearBounds(): ?array
    {
        return $this->vehicles->findFirstRegistrationYearBounds();
    }
}
