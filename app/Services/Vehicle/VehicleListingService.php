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
 *   - `listPaginatedSlim` · Index server-side (zéro calcul fiscal/rental ·
 *      sert en payload initial, les coûts arrivent en `Inertia::defer`).
 *   - `costsForVehicleIds` · map des coûts par véhicule (fiscaux + rental)
 *      pour la 2e vague defer · prewarm VFC batch (1 query SQL).
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
     * Variante **slim** dédiée à l'Index Flotte · liste paginée SANS
     * coûts (fullYearTax/dailyTaxRate/rentalPriceFullYear restent
     * `null`) · zéro pipeline fiscal, zéro batch rental.
     *
     * Le payload initial Inertia est servi immédiatement (juste les
     * colonnes SQL · plaque, marque/modèle, statut, date 1ère immat).
     * Les 3 colonnes calculées arrivent dans une 2e requête
     * `Inertia::defer` côté {@see VehicleController::index} qui appelle
     * {@see costsForVehicleIds}. Skeleton sur les 2 cellules le temps
     * du fetch différé.
     *
     * Doctrine `chargement-strict-par-ecran.md` · méthode dédiée par
     * usage · l'Index n'a pas besoin des coûts pour s'afficher.
     *
     * Cf. ADR-0020 D6 · le tri par `fullYearTax` est volontairement
     * absent de la whitelist sortKey (valeur calculée non SQL).
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
                // Coûts servis en `Inertia::defer` via `costsForVehicleIds`.
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
     * Map des coûts (taxe pleine + tarif location) pour un batch de
     * vehicleIds, pour l'année cible. Utilisée en `Inertia::defer` côté
     * {@see VehicleController::index} pour remplir les 3 cellules
     * calculées après le render initial slim.
     *
     * **Optimisation perf** ·
     *   - `prewarmFullYearForVehicles` · 1 query SQL batch qui pré-charge
     *     les segments VFC + pipeline results pour tous les véhicules
     *     · supprime le N+1 query VFC (N véhicules · N queries · -95 % SQL).
     *   - `rentalPrice->forVehiclesAndYear` · 2 SQL batched (vs 12 × N).
     *
     * **Équivalence stricte** garantie avec l'ancienne version `listPaginated` ·
     * `vehicleFullYearTax($v, $year)` retourne strictement la même valeur
     * que sans prewarm (cf. test FleetFiscalAggregatorTest::prewarm_equivalent_aux_appels_individuels).
     *
     * **Tolère année hors registry** · si pipeline fiscal n'a pas de règles
     * pour `$year`, affiche `0 €` plutôt que de crasher (cohérent doctrine
     * « données métier ⊥ règles fiscales » Phase 2).
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

        // Prewarm VFC + pipeline results en 1 query SQL batch · supprime
        // le N+1 query VFC dans la boucle `vehicleFullYearTax` ci-dessous.
        $this->aggregator->prewarmFullYearForVehicles($vehicles, $year);

        // Phase 13 D5.10.L · prix location batched · 2 SQL pour la page
        // entière au lieu de 12 × N SQL.
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
