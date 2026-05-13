<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Billing\BillingCalculationData;
use App\Data\User\Billing\BillingLineData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Contract;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Cœur du module Facturation V1.2 · calcule, pour une triplette
 * (entreprise utilisatrice × année × mois civil), la facture mensuelle
 * détaillée par véhicule.
 *
 * **Pipeline en 5 étapes** :
 *   1. Bornes du mois civil `[1er, dernier-du-mois]`.
 *   2. Récupération des contrats chevauchant cette fenêtre pour
 *      l'entreprise (eager-load `vehicle`).
 *   3. Pour chaque véhicule unique : énumération des dates utilisées
 *      (dédupliquées si plusieurs contrats successifs sur le mois).
 *   4. Vérification exhaustive de la présence des tarifs annuels :
 *      tout véhicule sans tarif est collecté, et l'exception est levée
 *      **après** le scan complet (UX : voir tous les manquants en une
 *      fois plutôt que les corriger un à un).
 *   5. Application de l'algorithme `OptimalRateBreakdown` puis
 *      composition des `BillingLineData` triées par plaque.
 *
 * **Conformité ADR-0013** : service pur applicatif, zéro SQL ici. Les
 * lectures passent par les repositories injectés.
 */
final readonly class BillingCalculator
{
    public function __construct(
        private ContractReadRepositoryInterface $contractRepository,
        private VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
    ) {}

    /**
     * @throws MissingPricingException si au moins un véhicule présent
     *                                 sur la période n'a pas de tarif
     *                                 défini pour l'année concernée.
     * @throws \InvalidArgumentException si le mois est hors [1, 12].
     */
    public function calculate(int $companyId, int $year, int $month): BillingCalculationData
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be in [1, 12], got {$month}.");
        }

        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        $contracts = $this->contractRepository->findForCompanyInPeriod(
            $companyId,
            $monthStart->toDateString(),
            $monthEnd->toDateString(),
        );

        if ($contracts->isEmpty()) {
            return new BillingCalculationData(
                companyId: $companyId,
                year: $year,
                month: $month,
                lines: [],
                totalCents: 0,
            );
        }

        // Étape 3 : daysUsed par véhicule (dédoublonnage par set de dates).
        $daysByVehicle = $this->aggregateDaysByVehicle($contracts, $monthStart, $monthEnd);

        // Étape 4 : vérification exhaustive des tarifs · batch lookup
        // unique sur tous les véhicules présents ce mois (élimine N+1
        // sur boucles `byCompanyForYear` × 12 mois).
        $vehicles = $this->indexVehiclesById($contracts);
        $vehicleIds = array_keys($daysByVehicle);
        $pricings = $this->pricingRepository->findForVehiclesAndYear($vehicleIds, $year);

        $missing = [];
        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricings[$vehicleId])) {
                $missing[] = [
                    'vehicleId' => $vehicleId,
                    'licensePlate' => $vehicles[$vehicleId]->license_plate,
                    'year' => $year,
                ];
            }
        }

        if ($missing !== []) {
            throw MissingPricingException::forMissingItems($missing);
        }

        // Étape 5 : composition des lignes triées par plaque.
        $lines = [];
        foreach ($daysByVehicle as $vehicleId => $daysUsed) {
            $vehicle = $vehicles[$vehicleId];
            $pricing = $pricings[$vehicleId];

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            $lines[] = new BillingLineData(
                vehicleId: $vehicleId,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $daysUsed,
                monthsBilled: $breakdown->months,
                weeksBilled: $breakdown->weeks,
                daysBilled: $breakdown->days,
                dailyRateCents: $pricing->daily_rate_cents,
                weeklyRateCents: $pricing->weekly_rate_cents,
                monthlyRateCents: $pricing->monthly_rate_cents,
                totalCents: $breakdown->totalCents,
            );
        }

        usort(
            $lines,
            static fn (BillingLineData $a, BillingLineData $b): int => strcmp($a->licensePlate, $b->licensePlate),
        );

        $total = array_sum(array_map(static fn (BillingLineData $l): int => $l->totalCents, $lines));

        return new BillingCalculationData(
            companyId: $companyId,
            year: $year,
            month: $month,
            lines: $lines,
            totalCents: $total,
        );
    }

    /**
     * Calcule en batch les 12 factures mensuelles d'une entreprise sur
     * une année · 1 SQL pour les contrats de l'année + 1 SQL batched
     * pour les pricings des véhicules impliqués, puis itération
     * in-memory mois par mois.
     *
     * Sémantique strictement identique à 12 appels successifs à
     * `calculate()` mais sans les 12 round-trips SQL (utile pour
     * `byCompanyForYear` et les résolveurs « pending »).
     *
     * @return array<int, BillingCalculationData|MissingPricingException>
     *                                                                    Clé : mois `[1..12]`. Une `MissingPricingException` à la place
     *                                                                    de la `BillingCalculationData` quand le mois aurait levé.
     */
    public function calculateYear(int $companyId, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = $yearStart->endOfYear();

        $allContracts = $this->contractRepository->findForCompanyInPeriod(
            $companyId,
            $yearStart->toDateString(),
            $yearEnd->toDateString(),
        );

        // Pré-batch les pricings de tous les véhicules de l'année (1 SQL).
        $allVehicleIds = [];
        foreach ($allContracts as $contract) {
            $allVehicleIds[$contract->vehicle_id] = true;
        }
        $pricingsAll = $allVehicleIds === []
            ? []
            : $this->pricingRepository->findForVehiclesAndYear(array_keys($allVehicleIds), $year);

        $vehiclesAll = $this->indexVehiclesById($allContracts);

        $results = [];
        for ($month = 1; $month <= 12; $month++) {
            $results[$month] = $this->calculateMonthFromPreloaded(
                $companyId,
                $year,
                $month,
                $allContracts,
                $pricingsAll,
                $vehiclesAll,
            );
        }

        return $results;
    }

    /**
     * Variante in-memory de `calculate()` qui prend les contrats/pricings
     * pré-chargés en amont · sert au mode batched `calculateYear`.
     *
     * @param  iterable<int, Contract>  $allContracts  contrats year-scoped (déjà filtrés sur company)
     * @param  array<int, mixed>  $pricingsAll  vehicleId → VehicleYearlyPricing
     * @param  array<int, Vehicle>  $vehiclesAll  vehicleId → Vehicle
     */
    private function calculateMonthFromPreloaded(
        int $companyId,
        int $year,
        int $month,
        iterable $allContracts,
        array $pricingsAll,
        array $vehiclesAll,
    ): BillingCalculationData|MissingPricingException {
        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        // Filtre les contrats qui chevauchent le mois.
        $monthContracts = [];
        foreach ($allContracts as $contract) {
            if ($contract->start_date->toDateString() > $monthEnd->toDateString()) {
                continue;
            }
            if ($contract->end_date->toDateString() < $monthStart->toDateString()) {
                continue;
            }
            $monthContracts[] = $contract;
        }

        if ($monthContracts === []) {
            return new BillingCalculationData(
                companyId: $companyId,
                year: $year,
                month: $month,
                lines: [],
                totalCents: 0,
            );
        }

        $daysByVehicle = $this->aggregateDaysByVehicle($monthContracts, $monthStart, $monthEnd);
        $vehicleIds = array_keys($daysByVehicle);

        $missing = [];
        foreach ($vehicleIds as $vehicleId) {
            if (! isset($pricingsAll[$vehicleId])) {
                $missing[] = [
                    'vehicleId' => $vehicleId,
                    'licensePlate' => $vehiclesAll[$vehicleId]->license_plate ?? "#{$vehicleId}",
                    'year' => $year,
                ];
            }
        }

        if ($missing !== []) {
            return MissingPricingException::forMissingItems($missing);
        }

        $lines = [];
        foreach ($daysByVehicle as $vehicleId => $daysUsed) {
            $vehicle = $vehiclesAll[$vehicleId];
            $pricing = $pricingsAll[$vehicleId];

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $daysUsed,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );

            $lines[] = new BillingLineData(
                vehicleId: $vehicleId,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $daysUsed,
                monthsBilled: $breakdown->months,
                weeksBilled: $breakdown->weeks,
                daysBilled: $breakdown->days,
                dailyRateCents: $pricing->daily_rate_cents,
                weeklyRateCents: $pricing->weekly_rate_cents,
                monthlyRateCents: $pricing->monthly_rate_cents,
                totalCents: $breakdown->totalCents,
            );
        }

        usort(
            $lines,
            static fn (BillingLineData $a, BillingLineData $b): int => strcmp($a->licensePlate, $b->licensePlate),
        );

        $total = array_sum(array_map(static fn (BillingLineData $l): int => $l->totalCents, $lines));

        return new BillingCalculationData(
            companyId: $companyId,
            year: $year,
            month: $month,
            lines: $lines,
            totalCents: $total,
        );
    }

    /**
     * Calcule, pour un véhicule donné sur un mois civil, le **total de
     * recettes** cross-entreprises (somme des facturations couples
     * `(vehicle × company × month)`). Sémantique : un véhicule loué à
     * deux entreprises distinctes le même mois génère deux factures
     * indépendantes · chacune avec son propre choix de combo
     * (jour/semaine/mois) optimal · et la recette véhicule du mois est
     * la somme des deux factures.
     *
     * **NB important** : on ne peut PAS sommer les jours cross-entreprises
     * puis appliquer une seule fois `OptimalRateBreakdown` · ce serait
     * sémantiquement faux car chaque facture est émise séparément
     * (10 j × 2 = 154 000 cts, ≠ 20 j en une fois = 150 000 cts avec
     * tarifs réalistes 90/500/1800).
     *
     * Retourne également `daysUsed` (somme cross-entreprises, dédup
     * intra-entreprise) pour alimenter le récap véhicule.
     *
     * @return array{daysUsed: int, totalCents: int}
     *
     * @throws MissingPricingException si le véhicule n'a pas de tarif
     *                                 défini pour l'année (un seul item
     *                                 dans la liste : c'est un calcul
     *                                 ciblé sur 1 véhicule).
     * @throws \InvalidArgumentException si le mois est hors [1, 12].
     */
    public function calculateForVehicleAndMonth(int $vehicleId, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be in [1, 12], got {$month}.");
        }

        $monthStart = CarbonImmutable::create($year, $month, 1);
        $monthEnd = $monthStart->endOfMonth();

        $contracts = $this->contractRepository->findWindowContractsForVehicle(
            $vehicleId,
            $monthStart,
            $monthEnd,
        );

        if ($contracts->isEmpty()) {
            return ['daysUsed' => 0, 'totalCents' => 0];
        }

        $pricing = $this->pricingRepository->findForVehicleAndYear($vehicleId, $year);

        if ($pricing === null) {
            // On a besoin d'une plaque pour le message UX : toutes les
            // contrats portent le même véhicule donc n'importe lequel
            // suffit (eager-load `vehicle` n'est pas garanti par
            // `findWindowContractsForVehicle` ; on requête explicitement).
            $first = $contracts->first();
            $licensePlate = $first?->vehicle->license_plate ?? "#{$vehicleId}";

            throw MissingPricingException::forMissingItems([
                ['vehicleId' => $vehicleId, 'licensePlate' => $licensePlate, 'year' => $year],
            ]);
        }

        // Group by company, dedup days, apply OptimalRateBreakdown per
        // (vehicle × company × month) couple, sum the totals.
        $datesByCompany = $this->expandContractsByKey(
            $contracts,
            $monthStart,
            $monthEnd,
            static fn (Contract $contract): int => $contract->company_id,
        );

        $daysUsed = 0;
        $totalCents = 0;

        foreach ($datesByCompany as $datesSet) {
            $perCompanyDays = count($datesSet);
            $daysUsed += $perCompanyDays;

            $breakdown = OptimalRateBreakdown::compute(
                daysUsed: $perCompanyDays,
                dailyCents: $pricing->daily_rate_cents,
                weeklyCents: $pricing->weekly_rate_cents,
                monthlyCents: $pricing->monthly_rate_cents,
            );
            $totalCents += $breakdown->totalCents;
        }

        return ['daysUsed' => $daysUsed, 'totalCents' => $totalCents];
    }

    /**
     * Agrège les jours utilisés par véhicule sur la fenêtre `[start, end]`,
     * en dédoublonnant les dates communes à plusieurs contrats. Préserve
     * l'ordre d'apparition des véhicules (pas de tri ici · le tri par
     * plaque est appliqué en aval).
     *
     * @param  iterable<int, Contract>  $contracts
     * @return array<int, int> vehicleId → daysUsed
     */
    private function aggregateDaysByVehicle(
        iterable $contracts,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
    ): array {
        $datesByVehicle = $this->expandContractsByKey(
            $contracts,
            $monthStart,
            $monthEnd,
            static fn (Contract $contract): int => $contract->vehicle_id,
        );

        return array_map(static fn (array $set): int => count($set), $datesByVehicle);
    }

    /**
     * Expansion d'une collection de contrats en `array<key, set-of-dates>`
     * sur la fenêtre `[monthStart, monthEnd]`, en appliquant le clipping
     * `exit_date` (cohérence ADR-0018) et la déduplication intra-clé.
     *
     * Helper partagé entre {@see aggregateDaysByVehicle} (clé
     * `vehicle_id`, alimente le pipeline `calculate()` côté entreprise)
     * et {@see calculateForVehicleAndMonth} (clé `company_id`, alimente
     * le récap véhicule cross-entreprises).
     *
     * **Clip à `exit_date`** (defense in depth) : la Validation Rule
     * `AvailableForPeriod` bloque normalement la création d'un contrat
     * débordant `exit_date`, mais on protège contre toute incohérence
     * résiduelle (modification post-création de `exit_date`, données
     * héritées, etc.).
     *
     * @template TKey of int|string
     *
     * @param  iterable<int, Contract>  $contracts
     * @param  callable(Contract): TKey  $keyOf
     * @return array<TKey, array<string, true>>
     */
    private function expandContractsByKey(
        iterable $contracts,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        callable $keyOf,
    ): array {
        /** @var array<TKey, array<string, true>> $byKey */
        $byKey = [];

        foreach ($contracts as $contract) {
            $clipStart = $contract->start_date->isAfter($monthStart)
                ? CarbonImmutable::parse($contract->start_date->toDateString())
                : $monthStart;
            $clipEnd = $contract->end_date->isBefore($monthEnd)
                ? CarbonImmutable::parse($contract->end_date->toDateString())
                : $monthEnd;

            $exitDate = $contract->vehicle?->exit_date;
            if ($exitDate !== null) {
                $exitDateImmutable = CarbonImmutable::parse($exitDate->toDateString());
                if ($exitDateImmutable->isBefore($clipStart)) {
                    continue;
                }
                if ($exitDateImmutable->isBefore($clipEnd)) {
                    $clipEnd = $exitDateImmutable;
                }
            }

            if ($clipStart->isAfter($clipEnd)) {
                continue;
            }

            $key = $keyOf($contract);
            $cursor = $clipStart;
            while (! $cursor->isAfter($clipEnd)) {
                $byKey[$key][$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }

        return $byKey;
    }

    /**
     * Indexe les véhicules par id à partir des contrats eager-loadés.
     * Suppose que `vehicle` est chargé sur chaque contrat (cf. repo
     * `findForCompanyInPeriod`).
     *
     * @param  iterable<int, Contract>  $contracts
     * @return array<int, Vehicle>
     */
    private function indexVehiclesById(iterable $contracts): array
    {
        $byId = [];
        foreach ($contracts as $contract) {
            /** @var Vehicle $vehicle */
            $vehicle = $contract->vehicle;
            $byId[$vehicle->id] = $vehicle;
        }

        return $byId;
    }
}
