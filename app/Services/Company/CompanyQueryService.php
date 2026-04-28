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
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Orchestration des lectures du domaine Company vers les DTOs exposés.
 *
 * Pré-charge en bulk les véhicules concernés via le repository pour
 * éviter tout N+1 dans le calcul d'agrégats fiscaux par entreprise.
 */
final class CompanyQueryService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly AssignmentQueryService $assignments,
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
        $cumul = $this->assignments->loadAnnualCumul($year);

        $vehicleIds = [];
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehiclesById = $this->vehicles->findByIdsIndexed(array_keys($vehicleIds));

        $rows = $this->companies->findAllOrderedByName()
            ->map(fn (Company $c): CompanyListItemData => new CompanyListItemData(
                id: $c->id,
                legalName: $c->legal_name,
                shortCode: $c->short_code,
                color: $c->color,
                siren: $c->siren,
                city: $c->city,
                isActive: $c->is_active,
                daysUsed: $cumul->daysByCompany($c->id),
                annualTaxDue: $this->aggregator->companyAnnualTax($c->id, $vehiclesById, $cumul, $year),
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
