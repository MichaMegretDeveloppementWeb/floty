<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Orchestrates the fiscal computation of a declaration for a
 * `(company, year)` pair, integrating human review decisions
 * (ADR-0015 § D5.8).
 *
 * Declaration tax is not a standard fiscal computation · it applies
 * the user's « Requalified » decisions on risky LCD clusters by
 * removing the R-2024-021 exemption from the affected contracts. The
 * legal rule stays pure; the runtime opt-outs live in this engine.
 *
 * Pipeline ·
 *   1. Re-detect clusters through {@see RiskDetectionService}
 *      (deterministic by fingerprint).
 *   2. Load persisted decisions; match decisions ↔ clusters by
 *      `cluster_fingerprint` via {@see DeclarationDecisionResolver}.
 *   3. For each cluster matched as `Requalified` · extract member
 *      `contractId`s → runtime opt-out.
 *   4. Build an ad-hoc {@see App\Services\Fiscal\FleetFiscalAggregator}
 *      via {@see DeclarationAggregatorFactory} (overlay of
 *      R-YYYY-021 with the `WithOptOuts` decorator).
 *   5. Compute tax `companyAnnualTaxBreakdownByVehicle()`.
 *   6. Proportional split at the contract level ·
 *      `contractTax = (contractDays / coupleDays) × coupleTax`.
 *      Each entry is enriched with cluster fingerprint / riskCode /
 *      decision, vehicle fiscal characteristics summary, opt-out flag.
 *   7. Global sort by `(startDate, vehicleId, contractId)` ASC so the
 *      frontend can roll up contiguous LCD-cluster contracts naturally.
 *   8. Compose the immutable {@see FiscalDeclarationSnapshot}.
 *
 * No-regression invariant · without any `Requalified` decision, the
 * snapshot totals stay strictly equal to the standard computation via
 * `FleetFiscalAggregator::companyAnnualTax()`. The per-contract split
 * never introduces a visible rounding divergence thanks to
 * `assertEqualsWithDelta(0.001)` in tests.
 *
 * SRP · cluster ↔ decision matching and aggregator overlay factory
 * were extracted into {@see DeclarationDecisionResolver} and
 * {@see DeclarationAggregatorFactory}; this engine focuses on
 * orchestration and snapshot composition.
 */
final readonly class DeclarationFiscalEngine
{
    public function __construct(
        private RiskDetectionService $detection,
        private FiscalReviewDecisionReadRepositoryInterface $decisions,
        private CompanyReadRepositoryInterface $companies,
        private ContractQueryService $contracts,
        private VehicleReadRepositoryInterface $vehicles,
        private LcdQualifier $lcdQualifier,
        private DeclarationDecisionResolver $decisionResolver,
        private DeclarationAggregatorFactory $aggregatorFactory,
    ) {}

    public function compute(int $companyId, int $year): FiscalDeclarationSnapshot
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            throw new RuntimeException(sprintf('Entreprise %d introuvable.', $companyId));
        }

        $clusters = $this->detection->detectClusters($companyId, $year);
        $persistedDecisions = $this->decisions->findAllForCompanyYear($companyId, $year);

        [$optOutContractIds, $appliedDecisions] = $this->decisionResolver->buildAppliedDecisionsAndOptOuts(
            $clusters,
            $persistedDecisions,
        );

        // Resolves traceability of "decisions reused from the
        // predecessor by fingerprint" so the frontend can render the
        // inherited-decision badge distinguishing reused vs
        // session-fresh decisions.
        $retainedFromByFingerprint = $this->decisionResolver->resolveRetainedFromMap(
            $companyId,
            $year,
            $persistedDecisions,
        );

        $contractClusterMap = $this->decisionResolver->buildContractClusterMap(
            $clusters,
            $appliedDecisions,
            $retainedFromByFingerprint,
        );

        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = array_keys($contractsByPair->pairsForCompany($companyId));

        $companyAddress = $this->formatCompanyAddress($company);

        if ($vehicleIds === []) {
            return new FiscalDeclarationSnapshot(
                companyId: $company->id,
                companyShortCode: $company->short_code,
                companyLegalName: $company->legal_name,
                fiscalYear: $year,
                computedAt: CarbonImmutable::now(),
                co2DueTotal: 0.0,
                pollutantsDueTotal: 0.0,
                totalDue: 0.0,
                contractBreakdown: [],
                appliedDecisions: $appliedDecisions,
                optOutContractIds: $optOutContractIds,
                companyAddress: $companyAddress,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        $aggregator = $this->aggregatorFactory->buildFor($year, $optOutContractIds);
        $rowsByVehicle = $aggregator->companyAnnualTaxBreakdownByVehicle(
            $companyId,
            $vehiclesById,
            $contractsByPair,
            $unavailabilitiesByVehicleId,
            $year,
        );

        $contractEntries = [];
        $totalCo2Raw = 0.0;
        $totalPollutantsRaw = 0.0;
        $pairsForCompany = $contractsByPair->pairsForCompany($companyId);

        foreach ($rowsByVehicle as $row) {
            $vehicleId = $row['vehicleId'];
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            $vehicleLabel = $this->buildVehicleLabel($vehicle);
            $vehicleFiscalSummary = $this->buildVehicleFiscalSummary($vehicle);

            $contractsForVehicle = $pairsForCompany[$vehicleId] ?? [];
            $contractsWithDays = $this->prepareContractsWithDays($contractsForVehicle, $year);

            $coupleCo2 = (float) $row['taxCo2'];
            $couplePollutants = (float) $row['taxPollutants'];

            // Accumulate totals at the couple level (already rounded by
            // `companyAnnualTaxBreakdownByVehicle`). Guarantees the
            // invariant · `snapshot.totalDue == standard computation`,
            // even if per-contract distribution introduces ±0.01 €
            // rounding differences on individual lines.
            $totalCo2Raw += $coupleCo2;
            $totalPollutantsRaw += $couplePollutants;

            // Compute taxable days per contract. An LCD contract is
            // exempt by R-2024-021 unless it has been opt-out by a
            // cluster decision (Requalified). The couple tax
            // `$coupleCo2` is already computed with R-2024-021 applied,
            // so it covers only the couple's non-exempt days. The
            // per-contract split must therefore use taxable days, not
            // raw days, otherwise the exempt LCDs would unduly receive
            // a slice of the tax.
            //
            // Vehicle-level exemption reasons are propagated from the
            // pipeline (electric / hydrogen, handicap, OIG, conditional
            // hybrid, specific activity, reductive unavailability, …).
            // These apply to ALL contracts of the `(company, vehicle)`
            // couple since they come from the vehicle.
            //
            // R-{year}-021 (LCD short-term rental) is excluded from the
            // propagated set · it is a per-contract exemption (cluster
            // opt-out possible) handled separately with a dedicated
            // wording below.
            $lcdRuleCode = sprintf('R-%d-021', $year);
            $vehicleLevelReasons = [];
            foreach ($row['appliedExemptions'] as $exemption) {
                if ($exemption->ruleCode === $lcdRuleCode) {
                    continue;
                }
                $vehicleLevelReasons[] = $exemption->reason;
            }

            $contractsWithTaxableDays = [];
            $coupleTaxableDays = 0;
            foreach ($contractsWithDays as $entry) {
                /** @var Contract $contract */
                $contract = $entry['contract'];
                $daysInYear = $entry['days'];

                $isLcd = $this->lcdQualifier->isShortTermRental($contract);
                $isExempted = $isLcd && ! in_array($contract->id, $optOutContractIds, true);
                $taxableDays = $isExempted ? 0 : $daysInYear;

                $contractReasons = $vehicleLevelReasons;
                if ($isExempted) {
                    $contractReasons[] = sprintf(
                        'Exonéré R-%d-021 · LCD courte durée (CIBS L. 421-129)',
                        $year,
                    );
                }
                $exemptionReason = $contractReasons === []
                    ? null
                    : implode(' · ', $contractReasons);

                $contractsWithTaxableDays[] = [
                    'contract' => $contract,
                    'days' => $daysInYear,
                    'taxableDays' => $taxableDays,
                    'exemptionReason' => $exemptionReason,
                ];
                $coupleTaxableDays += $taxableDays;
            }

            foreach ($contractsWithTaxableDays as $entry) {
                /** @var Contract $contract */
                $contract = $entry['contract'];
                $daysInYear = $entry['days'];
                $taxableDays = $entry['taxableDays'];
                $exemptionReason = $entry['exemptionReason'];

                $share = $coupleTaxableDays > 0 ? $taxableDays / $coupleTaxableDays : 0.0;
                $co2Due = round($coupleCo2 * $share, 2, PHP_ROUND_HALF_UP);
                $pollutantsDue = round($couplePollutants * $share, 2, PHP_ROUND_HALF_UP);
                $totalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

                $clusterInfo = $contractClusterMap[$contract->id] ?? null;

                $contractEntries[] = new ContractSnapshotEntry(
                    contractId: $contract->id,
                    contractReference: $contract->contract_reference,
                    contractType: $contract->contract_type,
                    startDate: $contract->start_date->toDateString(),
                    endDate: $contract->end_date->toDateString(),
                    daysInYearAssigned: $daysInYear,
                    vehicleId: $vehicleId,
                    vehicleLabel: $vehicleLabel,
                    vehicleFiscalSummary: $vehicleFiscalSummary,
                    co2Due: $co2Due,
                    pollutantsDue: $pollutantsDue,
                    totalDue: $totalDue,
                    clusterFingerprint: $clusterInfo['fingerprint'] ?? null,
                    clusterRiskCode: $clusterInfo['riskCode'] ?? null,
                    clusterRiskLevel: $clusterInfo['riskLevel'] ?? null,
                    clusterDecision: $clusterInfo['decision'] ?? null,
                    clusterJustification: $clusterInfo['justification'] ?? null,
                    clusterDecisionRetainedFrom: $clusterInfo['retainedFrom'] ?? null,
                    isOptedOut: in_array($contract->id, $optOutContractIds, true),
                    exemptionReason: $exemptionReason,
                );
            }
        }

        // Strict chronological snapshot ordering produces a readable
        // flat breakdown (no pseudo-groups by vehicle that would
        // scatter multi-vehicle clusters). Stable order ·
        // `(startDate, vehicleId, contractId)` ASC.
        usort(
            $contractEntries,
            static function (ContractSnapshotEntry $a, ContractSnapshotEntry $b): int {
                $cmp = strcmp($a->startDate, $b->startDate);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $a->vehicleId <=> $b->vehicleId;
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $a->contractId <=> $b->contractId;
            },
        );

        // CIBS L. 131-1 + BOI-AIS-MOB-10-30-10 · « the total amount due
        // by each taxpayer is rounded to the nearest euro, without
        // intermediate rounding ». A single rounding on `totalDue` (the
        // only declaratory amount). `co2DueTotal` and
        // `pollutantsDueTotal` stay at centime precision · they are
        // breakdown lines for the PDF and Show page, not declaratory
        // values.
        //
        // Cross-engine invariant · `totalDue` ==
        // `FleetFiscalAggregator::companyAnnualTax` for the same
        // `(company, year)` when no review decision applies. Covered by
        // `DeclarationFiscalEngineTest::standardAggregatorTotalFor`.
        return new FiscalDeclarationSnapshot(
            companyId: $company->id,
            companyShortCode: $company->short_code,
            companyLegalName: $company->legal_name,
            fiscalYear: $year,
            computedAt: CarbonImmutable::now(),
            co2DueTotal: round($totalCo2Raw, 2, PHP_ROUND_HALF_UP),
            pollutantsDueTotal: round($totalPollutantsRaw, 2, PHP_ROUND_HALF_UP),
            totalDue: round($totalCo2Raw + $totalPollutantsRaw, 0, PHP_ROUND_HALF_UP),
            contractBreakdown: $contractEntries,
            appliedDecisions: $appliedDecisions,
            optOutContractIds: $optOutContractIds,
            companyAddress: $companyAddress,
        );
    }

    /**
     * Frozen company postal address for the PDF · concatenates the
     * populated components with newlines · street (line 1 + line 2 on
     * the same line when both present), locality (`{postal_code} {city}`),
     * country (only when different from FR to keep noise low). Returns
     * `null` if every part is empty.
     */
    private function formatCompanyAddress(Company $company): ?string
    {
        $lines = [];

        $street = trim(implode(' ', array_filter([
            $company->address_line_1,
            $company->address_line_2,
        ])));
        if ($street !== '') {
            $lines[] = $street;
        }

        $locality = trim(implode(' ', array_filter([
            $company->postal_code,
            $company->city,
        ])));
        if ($locality !== '') {
            $lines[] = $locality;
        }

        if ($company->country !== '' && strtoupper($company->country) !== 'FR') {
            $lines[] = strtoupper($company->country);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Sorts a vehicle's contracts by `startDate ASC` and computes
     * their length in the target year. Date ordering ensures natural
     * visual grouping of LCD clusters on the frontend.
     *
     * @param  iterable<Contract>  $contracts
     * @return list<array{contract: Contract, days: int}>
     */
    private function prepareContractsWithDays(iterable $contracts, int $year): array
    {
        $rows = [];
        foreach ($contracts as $contract) {
            $rows[] = [
                'contract' => $contract,
                'days' => $contract->countDaysInYear($year),
            ];
        }
        usort(
            $rows,
            static fn (array $a, array $b): int => strcmp(
                $a['contract']->start_date->toDateString(),
                $b['contract']->start_date->toDateString(),
            ),
        );

        return $rows;
    }

    private function buildVehicleLabel(Vehicle $vehicle): string
    {
        return sprintf(
            '%s %s · %s',
            $vehicle->brand,
            $vehicle->model,
            $vehicle->license_plate,
        );
    }

    /**
     * Compact summary of the vehicle's fiscal characteristics
     * (reception category · CO₂ method · Euro standard), rendered by
     * `<ContractRow>` so an auditor can sanity-check the computed
     * amount at a glance.
     *
     * Format · `M1 · WLTP 100 g · Euro 6` (3 segments). Adapts to
     * NEDC/PA (fiscal horsepower) and to vehicles without an active
     * VFC (placeholder « VFC absente »).
     */
    private function buildVehicleFiscalSummary(Vehicle $vehicle): string
    {
        $vfc = $vehicle->fiscalCharacteristics->first();
        if (! $vfc instanceof VehicleFiscalCharacteristics) {
            return 'VFC absente';
        }

        $segments = [$vfc->reception_category->value];

        $co2Method = match ($vfc->homologation_method) {
            HomologationMethod::Wltp => $vfc->co2_wltp !== null ? sprintf('WLTP %d g', $vfc->co2_wltp) : 'WLTP',
            HomologationMethod::Nedc => $vfc->co2_nedc !== null ? sprintf('NEDC %d g', $vfc->co2_nedc) : 'NEDC',
            HomologationMethod::Pa => $vfc->taxable_horsepower !== null ? sprintf('%d CV', $vfc->taxable_horsepower) : 'PA',
        };
        $segments[] = $co2Method;

        if ($vfc->euro_standard !== null) {
            $segments[] = $vfc->euro_standard->label();
        }

        return implode(' · ', $segments);
    }
}
