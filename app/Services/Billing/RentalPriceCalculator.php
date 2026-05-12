<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Exceptions\Billing\MissingPricingException;
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
        private BillingCalculator $billingCalculator,
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

        $start = CarbonImmutable::parse($contract->start_date->toDateString());
        $end = CarbonImmutable::parse($contract->end_date->toDateString());

        // Clipping exit_date (defense in depth, cohérent ADR-0018).
        $exitDate = $contract->vehicle?->exit_date;
        if ($exitDate !== null) {
            $exitImmutable = CarbonImmutable::parse($exitDate->toDateString());
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

            $pricing = $this->pricingRepo->findForVehicleAndYear(
                $contract->vehicle_id,
                $monthStart->year,
            );

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
     */
    public function forCompanyAndYear(int $companyId, int $year): ?int
    {
        try {
            $total = 0;
            for ($month = 1; $month <= 12; $month++) {
                $calculation = $this->billingCalculator->calculate($companyId, $year, $month);
                $total += $calculation->totalCents;
            }

            return $total;
        } catch (MissingPricingException) {
            return null;
        }
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
                $clipStart = CarbonImmutable::parse($contract->start_date->toDateString());
                $clipEnd = CarbonImmutable::parse($contract->end_date->toDateString());

                if ($clipStart->isBefore($yearStart)) {
                    $clipStart = $yearStart;
                }
                if ($clipEnd->isAfter($yearEnd)) {
                    $clipEnd = $yearEnd;
                }

                $exitDate = $contract->vehicle?->exit_date;
                if ($exitDate !== null) {
                    $exitImmutable = CarbonImmutable::parse($exitDate->toDateString());
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
