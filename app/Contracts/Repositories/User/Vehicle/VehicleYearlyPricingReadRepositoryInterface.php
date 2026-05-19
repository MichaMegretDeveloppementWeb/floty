<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Vehicle;

use App\Models\VehicleYearlyPricing;

/**
 * Reads on per-year vehicle day/week/month rates.
 *
 * Consumed by:
 *   - `VehicleYearlyPricing[Read|Write]Repository` (application
 *     consistency)
 *   - `BillingCalculator` to retrieve the rates to apply when computing
 *     an invoice
 *   - The presentation layer (`VehicleData`) to expose existing rates
 *     to the Vehicle Show UI
 */
interface VehicleYearlyPricingReadRepositoryInterface
{
    /**
     * Rate of a vehicle for a given year. Returns `null` if no rate
     * has been defined for that year.
     */
    public function findForVehicleAndYear(int $vehicleId, int $year): ?VehicleYearlyPricing;

    /**
     * All rates defined for a vehicle, sorted by year ascending. Empty
     * list if no rate has been defined yet.
     *
     * @return list<VehicleYearlyPricing>
     */
    public function findAllForVehicle(int $vehicleId): array;

    /**
     * Batched variant for Index/listing usage · loads rates for
     * several vehicles for a given year in a single SQL query. Avoids
     * N+1 on paginated pages.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, VehicleYearlyPricing> vehicleId → pricing (missing key when no rate)
     */
    public function findForVehiclesAndYear(array $vehicleIds, int $year): array;

    /**
     * Multi-year batched variant · loads in a single SQL query the
     * rates of several vehicles over several years. Indexed by
     * `[year][vehicleId]` to allow in-memory dispatch in aggregators
     * iterating per year (cf.
     * {@see App\Services\Billing\BillingBreakdownService::totalRecettesForYears}).
     *
     * @param  list<int>  $vehicleIds
     * @param  list<int>  $years
     * @return array<int, array<int, VehicleYearlyPricing>> year → vehicleId → pricing
     */
    public function findForVehiclesAndYears(array $vehicleIds, array $years): array;
}
