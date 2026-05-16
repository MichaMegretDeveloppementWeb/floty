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
 * Listings et helpers énumérationnels du domaine Company (extrait de
 * `CompanyQueryService` pour respecter SRP · Lot 4 D08 / F-11-004).
 *
 * Regroupe ·
 *   - `listPaginated` · Index server-side avec aggregates fiscaux par page
 *   - `listForOptions` · liste légère pour `<SelectInput>` (forms Contract,
 *      Invoice)
 *   - `colorOptions` · énumération de l'enum `CompanyColor` pour les forms
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
     * Index Companies paginé server-side (cf. ADR-0020) · **variante slim**.
     *
     * P0.1 (audit perf 2026-05-16) · ne calcule PAS le pipeline fiscal
     * (`annualTaxDue`) ni le rental calculator (`rentalPriceTotal`) au
     * render initial · les 2 colonnes restent `null` dans le DTO et
     * hydratent en 2e round-trip via la prop racine `costs` servie en
     * `Inertia::defer` cote `CompanyController::index()`. Gain mesure
     * ~250-375 ms cold sur 25 items.
     *
     * `daysUsed` reste calcule eager · donnee brute (pas de dependance
     * au pipeline fiscal), necessite seulement `loadContractsByPair($year)`
     * qui sert deja aux endpoints AJAX.
     *
     * Doctrine `chargement-strict-par-ecran.md` § 3 · l'Index charge
     * uniquement ce qu'il affiche d'office (skeleton pour le reste).
     */
    public function listPaginatedSlim(CompanyIndexQueryData $query, int $year): PaginatedCompanyListData
    {
        $paginator = $this->companies->paginateForIndex($query);

        // Pre-charge bulk uniquement pour `daysUsed` (donnee brute).
        // Le pipeline fiscal + rental sont servis via `costsForCompanyIds`
        // appele en `Inertia::defer` cote controller.
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
     * Calcule la map des couts (`annualTaxDue`, `rentalPriceTotal`)
     * pour un batch de companies donnees par leurs IDs. Utilise en
     * `Inertia::defer` cote `CompanyController::index` pour remplir
     * les 2 colonnes de couts apres le render initial de l'Index.
     *
     * Performance ·
     *   - Pre-charge bulk en 1 fois (`contractsByPair`, `vehiclesById`,
     *     `unavailabilitiesByVehicleId`) puis itere sur la page.
     *   - Pipeline fiscal `companyAnnualTax` per company + rental
     *     calculator per company.
     *
     * Audit perf 2026-05-16 / 03-company.md P0 #1.
     *
     * @param  list<int>  $companyIds
     * @return array<int, array{annualTaxDue: float, rentalPriceTotal: float|null}>
     */
    public function costsForCompanyIds(array $companyIds, int $year): array
    {
        if ($companyIds === []) {
            return [];
        }

        // Memes prefetchs que l'ancien `listPaginated` · 1 fois pour
        // tous les companies de la page (pas N+1).
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);
        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        $result = [];
        foreach ($companyIds as $companyId) {
            // Tolere annee hors registry fiscal · 0 € plutot que crash.
            try {
                $annualTaxDue = $this->aggregator->companyAnnualTax(
                    $companyId,
                    $vehiclesById,
                    $contractsByPair,
                    $unavailabilitiesByVehicleId,
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
     * Liste pour les `<SelectInput>`.
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
     * Couleurs disponibles pour un `<SelectInput>` (formulaire create).
     * Pas d'accès BDD : énumère un enum applicatif.
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
