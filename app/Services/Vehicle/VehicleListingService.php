<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Vehicle\PaginatedVehicleListData;
use App\Data\User\Vehicle\VehicleIndexQueryData;
use App\Data\User\Vehicle\VehicleListItemData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Vehicle;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Spatie\LaravelData\DataCollection;

/**
 * Listings et helpers énumérationnels du domaine Vehicle (extrait de
 * `VehicleQueryService` pour respecter SRP · Lot 4 D09 / F-14-003).
 *
 * Regroupe ·
 *   - `listPaginated` · Index server-side avec calcul fiscal par page
 *   - `listForOptions` · liste enrichie pour `<SelectInput>` form Contrat
 *      avec pré-calcul des taxes pleines par année du scope
 *   - `firstRegistrationYearBounds` · helper bounds années pour filtre Index
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
     * Index Vehicles paginé server-side (cf. ADR-0020).
     *
     * Le repo gère pagination + filtres `includeExited`/`status` + search
     * en SQL pur. Le service calcule ensuite `fullYearTax` + `dailyTaxRate`
     * uniquement pour les véhicules de la page courante.
     *
     * Cf. ADR-0020 D6 : le tri par `fullYearTax` est volontairement
     * absent de la whitelist sortKey (valeur calculée non SQL).
     */
    public function listPaginated(VehicleIndexQueryData $query, int $year): PaginatedVehicleListData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $paginator = $this->vehicles->paginateForIndex($query);
        /** @var list<Vehicle> $vehicles */
        $vehicles = $paginator->items();

        // Phase 13 D5.10.L · prix location batched · 2 SQL pour la page
        // entière au lieu de 12 × N SQL.
        $vehicleIds = array_map(static fn (Vehicle $v): int => $v->id, $vehicles);
        $rentalPricesByVehicle = $this->rentalPrice->forVehiclesAndYear($vehicleIds, $year);

        $items = array_map(
            fn (Vehicle $v): VehicleListItemData => $this->mapVehicleToListItem(
                $v,
                $year,
                $daysInYear,
                $rentalPricesByVehicle[$v->id] ?? null,
            ),
            $vehicles,
        );

        return new PaginatedVehicleListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    private function mapVehicleToListItem(Vehicle $v, int $year, int $daysInYear, ?int $rentalCents): VehicleListItemData
    {
        // Tolère une année hors registry fiscal (cohérent doctrine
        // « données métier ⊥ règles fiscales » Phase 2) : si le pipeline
        // fiscal n'a pas de règles pour `$year`, on affiche `0 €` plutôt
        // que de crasher tout l'Index.
        try {
            $fullYearTax = $this->aggregator->vehicleFullYearTax($v, $year);
        } catch (FiscalCalculationException) {
            $fullYearTax = 0.0;
        }

        return new VehicleListItemData(
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
            fullYearTax: $fullYearTax,
            dailyTaxRate: round($fullYearTax / $daysInYear, 2, PHP_ROUND_HALF_UP),
            // Phase 13 D5.10.L · prix location annuel cross-entreprises
            // pré-calculé en mode batched par `forVehiclesAndYear`. Null
            // si tarif annuel manquant.
            rentalPriceFullYear: $rentalCents === null ? null : $rentalCents / 100,
        );
    }

    /**
     * Liste pour les `<SelectInput>` des formulaires (drawer Contrats,
     * etc.). Inclut les véhicules sortis pour permettre la consultation
     * et l'édition rétroactive des contrats antérieurs (cf. ADR-0018 § 4).
     *
     * Le frontend distingue actifs/retirés via `isExited` (groupement
     * dans le picker, suffixe label « (retiré le DD/MM/YYYY) »).
     *
     * @return DataCollection<int, VehicleOptionData>
     */
    public function listForOptions(): DataCollection
    {
        // Scope d'années dynamique (basé sur les contrats existants).
        // Pour chaque véhicule, on calcule la Taxe pleine de chaque année
        // du scope : le form Contrat affichera la valeur de l'année de
        // `start_date` saisie (fallback année courante). Coût borné par
        // O(N véhicules × M années) avec M typiquement 3-5 · acceptable
        // car l'aggregator cache les résultats par (vehicleId, year).
        //
        // On bypass `findAllForOptions()` (sélection limitée à 6 colonnes)
        // car le pipeline fiscal a besoin des colonnes complètes du
        // véhicule + de toute la VFC history (multi-VFC supportée).
        $availableYears = $this->availableYears->availableYears();
        $vehiclesFull = $this->vehicles->findAllForOptionsWithFiscalHistory();

        $rows = [];
        foreach ($vehiclesFull as $v) {
            $exitDate = $v->exit_date?->format('Y-m-d');
            $label = sprintf('%s - %s %s', $v->license_plate, $v->brand, $v->model);

            $fullYearTaxByYear = [];
            foreach ($availableYears as $year) {
                try {
                    $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($v, $year);
                    $fullYearTaxByYear[$year] = $breakdown->total;
                } catch (FiscalCalculationException) {
                    // Année hors règles fiscales codées · on omet
                    // pour rester silencieux côté UI (le form
                    // retombera sur l'année par défaut affichée).
                }
            }

            $rows[] = new VehicleOptionData(
                id: $v->id,
                licensePlate: $v->license_plate,
                label: $label,
                isExited: $exitDate !== null,
                exitDate: $exitDate,
                fullYearTaxByYear: $fullYearTaxByYear,
            );
        }

        return VehicleOptionData::collect($rows, DataCollection::class);
    }

    /**
     * Bornes min/max d'année de 1ʳᵉ immatriculation parmi tous les
     * véhicules en BDD. Alimente le sélecteur de fourchette du filtre
     * Index. Retourne `null` si la flotte est vide (le frontend cache
     * alors le filtre).
     *
     * @return array{min: int, max: int}|null
     */
    public function firstRegistrationYearBounds(): ?array
    {
        return $this->vehicles->findFirstRegistrationYearBounds();
    }
}
