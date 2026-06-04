<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Company\CompanyColorOptionData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\CompanyListItemData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Company\PaginatedCompanyListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Company\CompanyColor;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Listings and enum helpers for the Company domain, extracted from
 * `CompanyQueryService` for SRP.
 *
 *   - `listPaginatedSlim` · server-side index without per-row fiscal
 *     computation.
 *   - `costsForCompanyIds` · per-company costs map served as a defer.
 *   - `listForOptions` · light list for `<SelectInput>` forms.
 *   - `colorOptions` · enumeration of `CompanyColor` for forms.
 */
final class CompanyListingService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly RentalPriceCalculator $rentalPrice,
    ) {}

    /**
     * Server-side paginated Index (ADR-0020), slim variant.
     *
     * Does NOT run the fiscal pipeline (`annualTaxDue`) or the rental
     * calculator (`rentalPriceTotal`) on initial render · both columns
     * stay `null` in the DTO and hydrate via the root `costs` prop
     * served as `Inertia::defer` from `CompanyController::index()`.
     * Saves ~250-375 ms cold on 25 items.
     *
     * `daysUsed` stays eager · raw data with no fiscal dependency,
     * needs only `loadContractsByPair($year)` which also powers AJAX
     * endpoints.
     */
    public function listPaginatedSlim(CompanyIndexQueryData $query, int $year): PaginatedCompanyListData
    {
        $paginator = $this->companies->paginateForIndex($query);

        // Pre-load bulk only for `daysUsed` (raw data). The fiscal
        // pipeline + rental are served via `costsForCompanyIds`,
        // called as `Inertia::defer` from the controller.
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        $items = array_map(
            static fn (Company $c): CompanyListItemData => new CompanyListItemData(
                id: $c->id,
                legalName: $c->legal_name,
                shortCode: $c->short_code,
                color: $c->color,
                siren: $c->siren,
                city: $c->city,
                isActive: $c->is_active,
                daysUsed: $contractsByPair->daysByCompany($c->id, $year),
                annualTaxDue: null,
                rentalPriceTotal: null,
            ),
            $paginator->items(),
        );

        return new PaginatedCompanyListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Per-company costs map (`annualTaxDue`, `rentalPriceTotal`) for a
     * batch of company ids. Used via `Inertia::defer` from
     * `CompanyController::index` to fill the two cost columns after
     * the initial render.
     *
     * One-shot bulk prefetch (`contractsByPair`, `vehiclesById`,
     * `vehicleEventsByVehicleId`), then iterate the page. The
     * fiscal pipeline `companyAnnualTax` and the rental calculator
     * run per company.
     *
     * @param  list<int>  $companyIds
     * @return array<int, array{annualTaxDue: float, rentalPriceTotal: float|null}>
     */
    public function costsForCompanyIds(array $companyIds, int $year): array
    {
        if ($companyIds === []) {
            return [];
        }

        // Same prefetch shape as the previous monolithic `listPaginated`,
        // run once for every company on the page (no N+1).
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);
        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $vehicleEventsByVehicleId = $this->contracts->loadVehicleEventsByVehicle($vehicleIdList);

        $result = [];
        foreach ($companyIds as $companyId) {
            try {
                $annualTaxDue = $this->aggregator->companyAnnualTax(
                    $companyId,
                    $vehiclesById,
                    $contractsByPair,
                    $vehicleEventsByVehicleId,
                    $year,
                );
            } catch (FiscalCalculationException) {
                $annualTaxDue = 0.0;
            }

            $rentalCents = $this->rentalPrice->forCompanyAndYear($companyId, $year);
            $rentalPriceTotal = $rentalCents === null ? null : $rentalCents / 100;

            $result[$companyId] = [
                'annualTaxDue' => $annualTaxDue,
                'rentalPriceTotal' => $rentalPriceTotal,
            ];
        }

        return $result;
    }

    /**
     * Companies as `<SelectInput>` options.
     *
     * @return DataCollection<int, CompanyOptionData>
     */
    public function listForOptions(): DataCollection
    {
        $rows = $this->companies->findAllForOptions()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return CompanyOptionData::collect($rows, DataCollection::class);
    }

    /**
     * Color options for the create form. No DB access · enumerates an
     * application enum.
     *
     * @return DataCollection<int, CompanyColorOptionData>
     */
    public function colorOptions(): DataCollection
    {
        $rows = array_map(
            static fn (CompanyColor $c): CompanyColorOptionData => new CompanyColorOptionData(
                value: $c->value,
                label: $c->label(),
            ),
            CompanyColor::cases(),
        );

        return CompanyColorOptionData::collect($rows, DataCollection::class);
    }
}
