<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Models\Contract;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;

/**
 * Wrapper applicatif autour de `BillingCalculator` pour exposer 3 calculs
 * de « prix location » aux écrans Index/Show qui consomment des DTOs (Phase
 * 13 D5.10.L) ·
 *   - `forContract(contractId)` · prix d'un contrat individuel, split par
 *     mois civils, OptimalRateBreakdown par mois, somme.
 *   - `forVehicleAndYear(vehicleId, year)` · prix d'un véhicule sur l'année
 *     (somme cross-entreprises des 12 mois).
 *   - `forCompanyAndYear(companyId, year)` · prix d'une entreprise sur
 *     l'année (somme des 12 factures mensuelles).
 *
 * Toutes les méthodes retournent `int|null` (cents). `null` signifie qu'au
 * moins un pricing manquant a empêché le calcul · l'UI affiche « · ».
 *
 * **Performance** · le mode batched `forVehiclesAndYear(array, year)` est
 * exposé pour les Index/Listings et factorise les requêtes en 2 SQL totales
 * (1 pour les pricings de tous les véhicules, 1 pour leurs contrats de
 * l'année), puis applique `OptimalRateBreakdown` en mémoire par
 * `(vehicle × month × company)`. Évite les N+1 sur les pages paginées.
 * Le mode single `forVehicleAndYear` délègue au batched pour rester
 * efficace même sur des appels isolés.
 */
final readonly class RentalPriceCalculator
{
    public function __construct(
        private ContractReadRepositoryInterface $contractRepo,
        private VehicleYearlyPricingReadRepositoryInterface $pricingRepo,
    ) {}

    /**
     * Montant loyer pour un contrat individuel · split par mois civils
     * (clipping `exit_date` cohérent ADR-0018), OptimalRateBreakdown par
     * mois, somme finale. Le tarif annuel utilisé est celui de l'année
     * de `start_date` (les contrats qui croisent une année civile sont
     * rares en pratique ; pour la robustesse on prend le pricing du
     * mois courant si disponible, sinon null).
     */
    public function forContract(int $contractId): ?int
    {
        $contract = $this->contractRepo->findByIdWithRelations($contractId);

        if ($contract === null) {
            return null;
        }

        return $this->forContractModel($contract);
    }

    /**
     * Variante de `forContract` qui prend un model déjà chargé en
     * mémoire · évite le re-fetch côté repository quand l'appelant
     * dispose déjà du model (typiquement les Index paginés). Pricings
     * récupérés par batch sur tous les contrats du même appelant via
     * `forContracts`.
     *
     * @param  array<string, VehicleYearlyPricing>|null  $pricingsByVehicleYearKey
     *                                                                              Clé : `vehicleId.year` (string). Si fourni, pas de SQL.
     */
    public function forContractModel(Contract $contract, ?array $pricingsByVehicleYearKey = null): ?int
    {
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $exitDate = $contract->vehicle?->exit_date;
        if ($exitDate !== null) {
            $exitImmutable = $exitDate->toImmutable();
            if ($exitImmutable->isBefore($start)) {
                return 0;
            }
            if ($exitImmutable->isBefore($end)) {
                $end = $exitImmutable;
            }
        }

        $total = 0;
        $cursor = $start->startOfMonth();

        while (! $cursor->isAfter($end)) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            $clipStart = $start->isAfter($monthStart) ? $start : $monthStart;
            $clipEnd = $end->isBefore($monthEnd) ? $end : $monthEnd;

            if ($clipStart->isAfter($clipEnd)) {
                $cursor = $cursor->addMonth();

                continue;
            }

            $daysUsed = (int) $clipStart->diffInDays($clipEnd) + 1;

            $pricing = $pricingsByVehicleYearKey !== null
                ? ($pricingsByVehicleYearKey[$contract->vehicle_id.'.'.$monthStart->year] ?? null)
                : $this->pricingRepo->findForVehicleAndYear($contract->vehicle_id, $monthStart->year);

            if ($pricing === null) {
                return null;
            }

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            $total += $breakdown->totalCents;

            $cursor = $cursor->addMonth();
        }

        return $total;
    }

    /**
     * Calcule en batch les loyers d'une liste de contrats déjà chargés
     * en mémoire · 1 SQL pour tous les pricings (vehicle × year)
     * impliqués, puis agrégation in-memory contrat par contrat.
     *
     * Cas d'usage : Index Contrats paginé (cf. `ContractQueryService::
     * listPaginated`) qui enrichit 25 contrats par page · sans batch
     * c'est ~25 × N mois requêtes pricings, avec batch c'est 1.
     *
     * @param  iterable<Contract>  $contracts
     * @return array<int, ?int> contractId → cents (null si tarif manquant)
     */
    public function forContracts(iterable $contracts): array
    {
        $vehicleYearKeys = [];
        $contractsList = [];

        foreach ($contracts as $contract) {
            $contractsList[] = $contract;
            $start = $contract->start_date->toImmutable();
            $end = $contract->end_date->toImmutable();

            $cursor = $start->startOfMonth();
            while (! $cursor->isAfter($end)) {
                $vehicleYearKeys[$contract->vehicle_id][$cursor->year] = true;
                $cursor = $cursor->addMonth();
            }
        }

        if ($contractsList === []) {
            return [];
        }

        // Batch lookup groupé par année (1 SQL par année traversée par
        // au moins un contrat · en pratique 1, parfois 2 sur des
        // contrats cross-year).
        $pricingsByKey = [];
        $yearToVehicleIds = [];
        foreach ($vehicleYearKeys as $vehicleId => $yearsSet) {
            foreach (array_keys($yearsSet) as $year) {
                $yearToVehicleIds[$year][] = $vehicleId;
            }
        }
        foreach ($yearToVehicleIds as $year => $vehicleIds) {
            $pricings = $this->pricingRepo->findForVehiclesAndYear(array_values(array_unique($vehicleIds)), (int) $year);
            foreach ($pricings as $vehicleId => $pricing) {
                $pricingsByKey[$vehicleId.'.'.$year] = $pricing;
            }
        }

        $results = [];
        foreach ($contractsList as $contract) {
            $results[$contract->id] = $this->forContractModel($contract, $pricingsByKey);
        }

        return $results;
    }

    /**
     * Montant loyer pour 1 véhicule sur 1 année (somme cross-entreprises
     * des 12 facturations mensuelles). Null si tarif annuel manquant.
     *
     * Délègue à `forVehiclesAndYear` (2 SQL au total) plutôt que de
     * boucler 12 fois `calculateForVehicleAndMonth` (12 SQL).
     */
    public function forVehicleAndYear(int $vehicleId, int $year): ?int
    {
        $results = $this->forVehiclesAndYear([$vehicleId], $year);

        return $results[$vehicleId] ?? null;
    }

    /**
     * Montant loyer pour 1 entreprise sur 1 année (somme des 12 factures
     * mensuelles). Null si au moins 1 véhicule de la company a un pricing
     * manquant pour l'année · l'utilisateur doit corriger avant de
     * pouvoir lire le total.
     *
     * **Performance** · 2 SQL totales (1 contrats année + 1 pricings batch)
     * puis agrégation in-memory par (vehicle × month). Évite le N+1 sur
     * les pages qui appellent cette méthode par année historique
     * (`computeYearStats` × N années sur la fiche entreprise).
     */
    public function forCompanyAndYear(int $companyId, int $year): ?int
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = $yearStart->endOfYear();

        $contracts = $this->contractRepo->findForCompanyInPeriod(
            $companyId,
            $yearStart->toDateString(),
            $yearEnd->toDateString(),
        );

        if ($contracts->isEmpty()) {
            return 0;
        }

        // Expansion par (mois × véhicule), déduplication des jours.
        $datesByMonthAndVehicle = [];
        $vehicleIds = [];

        foreach ($contracts as $contract) {
            $clipStart = $contract->start_date->isAfter($yearStart)
                ? $contract->start_date->toImmutable()
                : $yearStart;
            $clipEnd = $contract->end_date->isBefore($yearEnd)
                ? $contract->end_date->toImmutable()
                : $yearEnd;

            // Clipping `exit_date` (cohérence ADR-0018, defense in depth).
            $exitDate = $contract->vehicle?->exit_date;
            if ($exitDate !== null) {
                $exitImmutable = $exitDate->toImmutable();
                if ($exitImmutable->isBefore($clipStart)) {
                    continue;
                }
                if ($exitImmutable->isBefore($clipEnd)) {
                    $clipEnd = $exitImmutable;
                }
            }

            if ($clipStart->isAfter($clipEnd)) {
                continue;
            }

            $vehicleIds[$contract->vehicle_id] = true;

            $cursor = $clipStart;
            while (! $cursor->isAfter($clipEnd)) {
                $monthKey = $cursor->month;
                $datesByMonthAndVehicle[$monthKey][$contract->vehicle_id][$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }

        $vehicleIdsList = array_keys($vehicleIds);
        $pricings = $this->pricingRepo->findForVehiclesAndYear($vehicleIdsList, $year);

        foreach ($vehicleIdsList as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                return null;
            }
        }

        $total = 0;
        foreach ($datesByMonthAndVehicle as $byVehicle) {
            foreach ($byVehicle as $vehicleId => $datesSet) {
                $pricing = $pricings[$vehicleId];
                $breakdown = OptimalRateBreakdown::compute(
                    daysUsed: count($datesSet),
                    dailyCents: $pricing->daily_rate_cents,
                    weeklyCents: $pricing->weekly_rate_cents,
                    monthlyCents: $pricing->monthly_rate_cents,
                );
                $total += $breakdown->totalCents;
            }
        }

        return $total;
    }

    /**
     * Variante batched de `forVehicleAndYear` pour les Index/Listings ·
     * une seule SQL pour tous les pricings + une seule SQL pour tous les
     * contrats de l'année, puis agrégation en mémoire (Phase 13 D5.10.L).
     *
     * Évite les N+1 (12 calls × N véhicules = 12N SQL) au profit de 2 SQL
     * + travail in-memory.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, ?int> vehicleId → cents (null si tarif manquant)
     */
    public function forVehiclesAndYear(array $vehicleIds, int $year): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $pricings = $this->pricingRepo->findForVehiclesAndYear($vehicleIds, $year);
        $contracts = $this->contractRepo->findForVehiclesInYear($vehicleIds, $year);

        // Grouper les contrats par véhicule.
        $contractsByVehicle = [];
        foreach ($contracts as $contract) {
            $contractsByVehicle[$contract->vehicle_id][] = $contract;
        }

        $results = [];
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                $results[$vehicleId] = null;

                continue;
            }

            $pricing = $pricings[$vehicleId];
            $vehicleContracts = $contractsByVehicle[$vehicleId] ?? [];

            // Pour chaque (vehicle × month × company), on déduplique les
            // dates et applique OptimalRateBreakdown.
            // Sémantique cohérente avec BillingCalculator::calculateForVehicleAndMonth.
            $datesByMonthAndCompany = [];

            foreach ($vehicleContracts as $contract) {
                $clipStart = $contract->start_date->toImmutable();
                $clipEnd = $contract->end_date->toImmutable();

                if ($clipStart->isBefore($yearStart)) {
                    $clipStart = $yearStart;
                }
                if ($clipEnd->isAfter($yearEnd)) {
                    $clipEnd = $yearEnd;
                }

                $exitDate = $contract->vehicle?->exit_date;
                if ($exitDate !== null) {
                    $exitImmutable = $exitDate->toImmutable();
                    if ($exitImmutable->isBefore($clipStart)) {
                        continue;
                    }
                    if ($exitImmutable->isBefore($clipEnd)) {
                        $clipEnd = $exitImmutable;
                    }
                }

                $cursor = $clipStart;
                while (! $cursor->isAfter($clipEnd)) {
                    $monthKey = $cursor->format('Y-m');
                    $datesByMonthAndCompany[$monthKey][$contract->company_id][$cursor->toDateString()] = true;
                    $cursor = $cursor->addDay();
                }
            }

            $total = 0;
            foreach ($datesByMonthAndCompany as $byCompany) {
                foreach ($byCompany as $datesSet) {
                    $days = count($datesSet);
                    $breakdown = OptimalRateBreakdown::compute(
                        daysUsed: $days,
                        dailyCents: $pricing->daily_rate_cents,
                        weeklyCents: $pricing->weekly_rate_cents,
                        monthlyCents: $pricing->monthly_rate_cents,
                    );
                    $total += $breakdown->totalCents;
                }
            }

            $results[$vehicleId] = $total;
        }

        return $results;
    }
}
