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
 * Listings et helpers énumérationnels du domaine Vehicle (extrait de
 * `VehicleQueryService` pour respecter SRP · Lot 4 D09 / F-14-003).
 *
 * Regroupe ·
 *   - `listPaginated` · Index server-side avec calcul fiscal par page
 *   - `listForLightSelector` · liste slim (id, label, isExited) pour
 *      tous les sélecteurs UI (filter dropdown Index, form Create/Edit
 *      Contract). Zéro calcul fiscal · audit S2.4 + S2.5.
 *   - `fullYearTaxForVehicle` · calcul on-demand de la taxe pleine
 *      d'un véhicule pour l'endpoint AJAX du form Create/Edit Contract
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
     * Liste **slim** pour les sélecteurs UI · filter dropdown Index,
     * sélecteur véhicule du form Create/Edit Contract, picker dans le
     * planning, etc. Aucun calcul fiscal, 1 query SQL sur 6 colonnes
     * (cf. `findAllForOptions` du repo).
     *
     * **Doctrine** · méthodes dédiées par usage avec strict minimum.
     * Le calcul de taxe pleine année par véhicule (autrefois éager
     * dans `listForOptions`) est désormais on-demand via l'endpoint
     * `GET /app/vehicles/{vehicle}/full-year-tax` déclenché quand
     * l'utilisateur sélectionne effectivement un véhicule (composable
     * frontend `useVehicleFullYearTax`).
     *
     * Inclut les véhicules sortis (cf. ADR-0018 § 4 · permettre la
     * consultation et l'édition rétroactive des contrats antérieurs).
     *
     * Audit perf 2026-05-16 · S2.4 + S2.5 · éliminé 192 pipeline runs
     * par chargement de page Index/Create/Edit Contract.
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
     * Calcul **on-demand** de la taxe pleine année d'un véhicule pour
     * une année cible. Sert l'endpoint AJAX déclenché par le composable
     * frontend `useVehicleFullYearTax` quand l'utilisateur sélectionne
     * un véhicule dans le form Create/Edit Contract (Calcul A · cf.
     * doctrine perf 2026-05-16).
     *
     * Si la `$targetYear` n'est pas dans le scope `AvailableYearsResolver`,
     * tente un fallback sur la plus récente année disponible. Retourne
     * `null` si aucune année du scope n'est calculable.
     *
     * @return array{cents: int, year: int, fallback: bool}|null
     */
    public function fullYearTaxForVehicle(Vehicle $vehicle, int $targetYear): ?array
    {
        // Essai direct sur l'année demandée.
        try {
            $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $targetYear);

            return [
                'cents' => (int) round($breakdown->total * 100),
                'year' => $targetYear,
                'fallback' => false,
            ];
        } catch (FiscalCalculationException) {
            // Fallback · descend dans le scope d'années connues.
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
