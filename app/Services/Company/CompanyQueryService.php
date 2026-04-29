<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Company\CompanyColorOptionData;
use App\Data\User\Company\CompanyListItemData;
use App\Data\User\Company\CompanyOptionData;
use App\Enums\Company\CompanyColor;
use App\Models\Company;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Company vers les DTOs exposés.
 *
 * Pré-charge en bulk les véhicules concernés via le repository pour
 * éviter tout N+1 dans le calcul d'agrégats fiscaux par entreprise.
 *
 * **Refonte 04.F (ADR-0014)** : `daysUsed` et `annualTaxDue` dérivés
 * de `ContractsByPair` au lieu de `AnnualCumulByPair`.
 */
final class CompanyQueryService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    /**
     * Liste des entreprises pour la page « Entreprises utilisatrices »
     * avec jours utilisés + taxe annuelle agrégée par entreprise.
     *
     * @return DataCollection<int, CompanyListItemData>
     */
    public function listForFleetView(int $year): DataCollection
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);
        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        $rows = $this->companies->findAllOrderedByName()
            ->map(fn (Company $c): CompanyListItemData => new CompanyListItemData(
                id: $c->id,
                legalName: $c->legal_name,
                shortCode: $c->short_code,
                color: $c->color,
                siren: $c->siren,
                city: $c->city,
                isActive: $c->is_active,
                daysUsed: $contractsByPair->daysByCompany($c->id, $year),
                annualTaxDue: $this->aggregator->companyAnnualTax(
                    $c->id,
                    $vehiclesById,
                    $contractsByPair,
                    $unavailabilitiesByVehicleId,
                    $year,
                ),
            ))
            ->values()
            ->all();

        return CompanyListItemData::collect($rows, DataCollection::class);
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
