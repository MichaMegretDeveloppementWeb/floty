<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Data\User\Company\CompanyColorOptionData;
use App\Data\User\Company\CompanyListItemData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Company\StoreCompanyData;
use App\Enums\Company\CompanyColor;
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Spatie\LaravelData\DataCollection;

/**
 * Requêtes lecture + créations sur le domaine Company.
 *
 * Précharge en une seule requête tous les véhicules concernés par
 * les attributions de l'année afin d'éviter tout N+1 dans le calcul
 * d'agrégats fiscaux par entreprise.
 */
final class CompanyQueryService
{
    public function __construct(
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

        // Pré-chargement bulk de tous les véhicules concernés.
        $vehicleIds = [];
        foreach ($cumul->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehiclesById = Vehicle::query()
            ->whereIn('id', array_keys($vehicleIds))
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->get()
            ->keyBy('id');

        $companies = Company::query()->orderBy('legal_name')->get();

        $rows = $companies
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
        $rows = Company::query()
            ->where('is_active', true)
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'short_code', 'color'])
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

    /**
     * Création d'une entreprise — wrapper trivial pour ne pas laisser
     * `Company::create()` dans le controller. Mapping explicite
     * camelCase → snake_case pour matcher les `Fillable` du modèle
     * (Spatie Data n'expose `MapInputName` que pour la désérialisation
     * entrante, pas pour `->all()`).
     */
    public function create(StoreCompanyData $data): Company
    {
        return Company::create([
            'legal_name' => $data->legalName,
            'short_code' => $data->shortCode,
            'color' => $data->color,
            'siren' => $data->siren,
            'siret' => $data->siret,
            'address_line_1' => $data->addressLine1,
            'address_line_2' => $data->addressLine2,
            'postal_code' => $data->postalCode,
            'city' => $data->city,
            'country' => $data->country,
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'contact_phone' => $data->contactPhone,
            'is_active' => $data->isActive,
        ]);
    }
}
