<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Company\CompanyFiscalYearData;
use App\Data\User\Company\CompanyVehicleFiscalRowData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Support\Carbon;

/**
 * Agrégats annuels pour les onglets Show Company (extrait de
 * `CompanyQueryService` pour respecter SRP · Lot 4 D08 / F-11-004).
 *
 * Regroupe les 2 méthodes qui composent un récap annuel d'une
 * entreprise pour un exercice fiscal donné ·
 *   - `billingForYear` · onglet Billing (récap mensuel facturation)
 *   - `fiscalBreakdownForYear` · onglet Fiscal (1 ligne par véhicule
 *      utilisé, totaux agrégés CO₂ + polluants)
 */
final class CompanyAggregatesService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly BillingBreakdownService $billingBreakdown,
    ) {}

    /**
     * Récap mensuel facturation pour une entreprise et une année donnée
     * (Phase 14.D V1.2). Sépare l'agrégation `BillingBreakdownService`
     * du flot principal `detail()` pour permettre un sélecteur d'année
     * indépendant côté UI (pattern aligné avec `fiscalBreakdownForYear`).
     */
    public function billingForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        return $this->billingBreakdown->byCompanyForYear($companyId, $year);
    }

    /**
     * Détail fiscal d'une entreprise pour l'année sélectionnée
     * (chantier N.2). 1 ligne par véhicule utilisé, totaux agrégés
     * (R-2024-003 : un seul arrondi par redevable).
     *
     * Si l'année n'est pas configurée dans le calculateur fiscal
     * (`config/floty.fiscal.available_years`), retourne des montants
     * à 0 plutôt que de faire crasher la page · l'utilisateur voit
     * quand même les jours et le compte de véhicules.
     */
    public function fiscalBreakdownForYear(int $companyId, int $year): CompanyFiscalYearData
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $currentRealYear = (int) Carbon::now()->year;
        $availableYears = $this->contracts->availableYearsRangeForCompany(
            $companyId,
            $currentRealYear,
        );

        $vehicleIds = [];
        $daysPerVehicle = [];
        // Phase 12 D5.9.A · compteur de contrats pour la carte recap
        // (toutes paires véhicule × company sur l'exercice).
        $totalContracts = 0;
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            $totalContracts += count($pairContracts);
            // Compteur de jours en pré-calcul, indépendant du pipeline
            // fiscal · utilisé pour la colonne `daysUsed` même si la
            // config fiscale de l'année est absente (cas FiscalCalculationException).
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += $contract->countDaysInYear($year);
            }
            $daysPerVehicle[$vehicleId] = $days;
        }

        if ($vehicleIds === []) {
            return new CompanyFiscalYearData(
                year: $year,
                currentRealYear: $currentRealYear,
                rows: [],
                availableYears: $availableYears,
                totalDays: 0,
                totalTaxCo2: 0.0,
                totalTaxPollutants: 0.0,
                totalTaxAll: 0.0,
                contractsCount: 0,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        // Calcul du pipeline fiscal · encadré pour tolérer l'absence de
        // config fiscale sur l'année (cf. doc).
        $taxRowsByVehicleId = [];
        try {
            $rawRows = $this->aggregator->companyAnnualTaxBreakdownByVehicle(
                $companyId,
                $vehiclesById,
                $contractsByPair,
                $unavailabilitiesByVehicleId,
                $year,
            );
            foreach ($rawRows as $rawRow) {
                $taxRowsByVehicleId[$rawRow['vehicleId']] = $rawRow;
            }
        } catch (FiscalCalculationException) {
            $taxRowsByVehicleId = [];
        }

        $daysInYear = Carbon::createFromDate($year, 1, 1)->isLeapYear() ? 366 : 365;

        $rows = [];
        $totalDays = 0;
        $totalTaxCo2Raw = 0.0;
        $totalTaxPollutantsRaw = 0.0;

        foreach ($vehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            // `daysUsed` toujours pris du pré-calcul brut (jours d'attribution
            // sur l'année), pas du pipeline qui retourne `daysAssigned`
            // potentiellement réduit par R-2024-008 (indispos) ou R-2024-021
            // (LCD < 30j hors période). On veut afficher le brut consommé.
            $days = (int) ($daysPerVehicle[$vehicleId] ?? 0);
            $taxRow = $taxRowsByVehicleId[$vehicleId] ?? null;
            $taxCo2 = $taxRow !== null ? (float) $taxRow['taxCo2'] : 0.0;
            $taxPollutants = $taxRow !== null ? (float) $taxRow['taxPollutants'] : 0.0;
            $taxTotal = $taxRow !== null ? (float) $taxRow['taxTotal'] : 0.0;

            $proratoPercent = round($days / $daysInYear * 100, 1);

            $rows[] = new CompanyVehicleFiscalRowData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                proratoPercent: $proratoPercent,
                taxCo2: $taxCo2,
                taxPollutants: $taxPollutants,
                taxTotal: $taxTotal,
            );

            $totalDays += $days;
            $totalTaxCo2Raw += $taxCo2;
            $totalTaxPollutantsRaw += $taxPollutants;
        }

        $totalTaxCo2 = round($totalTaxCo2Raw, 2, PHP_ROUND_HALF_UP);
        $totalTaxPollutants = round($totalTaxPollutantsRaw, 2, PHP_ROUND_HALF_UP);

        return new CompanyFiscalYearData(
            year: $year,
            currentRealYear: $currentRealYear,
            rows: $rows,
            availableYears: $availableYears,
            totalDays: $totalDays,
            totalTaxCo2: $totalTaxCo2,
            totalTaxPollutants: $totalTaxPollutants,
            totalTaxAll: round($totalTaxCo2 + $totalTaxPollutants, 2, PHP_ROUND_HALF_UP),
            contractsCount: $totalContracts,
        );
    }
}
