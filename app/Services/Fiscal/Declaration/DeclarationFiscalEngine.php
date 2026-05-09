<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Fiscal\ValueObjects\VehicleSnapshotEntry;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use App\Models\FiscalReviewDecision;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Orchestre le calcul fiscal d'une déclaration pour un couple
 * `(company, year)` en intégrant les décisions humaines de revue
 * (Phase 11 D5.2, ADR-0015 § 5.1 rev. 1.1).
 *
 * **Pourquoi un engine dédié** : le calcul d'une déclaration n'est pas
 * un calcul fiscal standard. Il applique en plus les décisions
 * « Requalified » prises par l'utilisateur sur les clusters LCD à
 * risque (D3-D4) en retirant l'exonération R-2024-021 aux contrats
 * concernés. La règle légale R-2024-021 reste pure (D5.1) ; les
 * opt-outs runtime sont portés par la **recette** (cet engine), pas
 * par la règle.
 *
 * **Pipeline d'exécution** :
 *   1. Re-détection des clusters via {@see RiskDetectionService}
 *      (déterministe par fingerprint, doctrine D2).
 *   2. Lecture des décisions persistées (D3) ; match decisions ↔ clusters
 *      par `cluster_fingerprint`.
 *   3. Pour chaque cluster matché en `Requalified` : extraction des
 *      `contractId` membres → opt-out runtime.
 *   4. Construction ad-hoc d'un {@see OverlayedRuleRegistry} +
 *      {@see RuleEffectiveSegmenter} + {@see FiscalSegmentedExecutor} +
 *      {@see FleetFiscalAggregator} frais (cf. D5.1).
 *   5. Calcul via `companyAnnualTaxBreakdownByVehicle` sur cet
 *      aggregator ad-hoc.
 *   6. Composition du {@see FiscalDeclarationSnapshot} immuable.
 *
 * **No-regression invariant** : sans aucune décision `Requalified`, le
 * snapshot produit des totaux **strictement identiques** au calcul
 * standard via `FleetFiscalAggregator::companyAnnualTax()` (le decorator
 * D5.1 wraps mais ne filtre rien quand la liste opt-out est vide).
 *
 * **Pas de persistance** : l'engine retourne le VO en mémoire. Sa
 * sérialisation BDD arrive en D5.5 (`GenerateDeclarationAction`
 * enrichi) ; son rendu PDF en D5.4.
 */
final readonly class DeclarationFiscalEngine
{
    public function __construct(
        private RiskDetectionService $detection,
        private FiscalReviewDecisionReadRepositoryInterface $decisions,
        private CompanyReadRepositoryInterface $companies,
        private ContractQueryService $contracts,
        private VehicleReadRepositoryInterface $vehicles,
        private VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
        private FiscalRuleRegistry $baseRegistry,
        private FiscalRuleReadRepositoryInterface $fiscalRules,
        private FiscalYearContext $yearContext,
        private Container $container,
    ) {}

    public function compute(int $companyId, int $year): FiscalDeclarationSnapshot
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            throw new RuntimeException(sprintf('Entreprise %d introuvable.', $companyId));
        }

        $clusters = $this->detection->detectClusters($companyId, $year);
        $persistedDecisions = $this->decisions->findAllForCompanyYear($companyId, $year);

        [$optOutContractIds, $appliedDecisions] = $this->buildAppliedDecisionsAndOptOuts(
            $clusters,
            $persistedDecisions,
        );

        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $vehicleIds = array_keys($contractsByPair->pairsForCompany($companyId));

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
                vehicleBreakdown: [],
                appliedDecisions: $appliedDecisions,
                optOutContractIds: $optOutContractIds,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        $aggregator = $this->buildAdHocAggregator($optOutContractIds);
        $rows = $aggregator->companyAnnualTaxBreakdownByVehicle(
            $companyId,
            $vehiclesById,
            $contractsByPair,
            $unavailabilitiesByVehicleId,
            $year,
        );

        $vehicleEntries = [];
        $totalCo2Raw = 0.0;
        $totalPollutantsRaw = 0.0;
        foreach ($rows as $row) {
            $vehicle = $vehiclesById->get($row['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $vehicleEntries[] = new VehicleSnapshotEntry(
                vehicleId: $row['vehicleId'],
                vehicleLabel: $this->buildVehicleLabel($vehicle),
                daysAssigned: $row['days'],
                co2Due: $row['taxCo2'],
                pollutantsDue: $row['taxPollutants'],
                totalDue: $row['taxTotal'],
            );
            $totalCo2Raw += $row['taxCo2'];
            $totalPollutantsRaw += $row['taxPollutants'];
        }

        return new FiscalDeclarationSnapshot(
            companyId: $company->id,
            companyShortCode: $company->short_code,
            companyLegalName: $company->legal_name,
            fiscalYear: $year,
            computedAt: CarbonImmutable::now(),
            co2DueTotal: round($totalCo2Raw, 2, PHP_ROUND_HALF_UP),
            pollutantsDueTotal: round($totalPollutantsRaw, 2, PHP_ROUND_HALF_UP),
            totalDue: round($totalCo2Raw + $totalPollutantsRaw, 2, PHP_ROUND_HALF_UP),
            vehicleBreakdown: $vehicleEntries,
            appliedDecisions: $appliedDecisions,
            optOutContractIds: $optOutContractIds,
        );
    }

    /**
     * Match decisions ↔ clusters par fingerprint. Pattern aligné sur
     * {@see App\Services\Fiscal\RiskDetection\DeclarationPreviewService::applyPersistedDecisions}.
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  iterable<FiscalReviewDecision>  $persistedDecisions
     * @return array{0: list<int>, 1: list<AppliedDecisionEntry>}
     */
    private function buildAppliedDecisionsAndOptOuts(
        array $clusters,
        iterable $persistedDecisions,
    ): array {
        $byFingerprint = [];
        foreach ($persistedDecisions as $decision) {
            $byFingerprint[$decision->cluster_fingerprint] = $decision;
        }

        $optOuts = [];
        $applied = [];
        foreach ($clusters as $cluster) {
            $match = $byFingerprint[$cluster->fingerprint] ?? null;
            if ($match === null) {
                continue;
            }

            $contractIds = array_map(
                static fn ($contract): int => $contract->contractId,
                $cluster->contracts,
            );

            $applied[] = new AppliedDecisionEntry(
                clusterFingerprint: $cluster->fingerprint,
                riskCode: $cluster->code,
                decision: $match->decision,
                contractIds: $contractIds,
                justification: $match->justification,
            );

            if ($match->decision === ReviewDecisionType::Requalified) {
                foreach ($contractIds as $contractId) {
                    $optOuts[] = $contractId;
                }
            }
        }

        $optOuts = array_values(array_unique($optOuts));
        sort($optOuts);

        return [$optOuts, $applied];
    }

    /**
     * Construit un `FleetFiscalAggregator` ad-hoc dont la chaîne pipeline
     * est branchée sur un {@see OverlayedRuleRegistry} qui substitue
     * R-2024-021 par {@see R2024_021_WithOptOuts} pour cette déclaration.
     *
     * Toutes les instances sont **fraîches** (caches scopés à
     * l'aggregator, pas de partage avec le singleton standard du
     * container).
     *
     * @param  list<int>  $optOutContractIds
     */
    private function buildAdHocAggregator(array $optOutContractIds): FleetFiscalAggregator
    {
        $wrappedRule = $this->container->make(R2024_021_ShortTermRental::class);
        $decorator = new R2024_021_WithOptOuts($wrappedRule, $optOutContractIds);

        $overlayedRegistry = new OverlayedRuleRegistry(
            $this->container,
            $this->baseRegistry,
            $decorator,
        );
        $segmenter = new RuleEffectiveSegmenter($overlayedRegistry);
        $pipeline = new FiscalPipeline(
            $overlayedRegistry,
            $this->yearContext,
            $this->vfcRepository,
        );
        $executor = new FiscalSegmentedExecutor(
            $this->vfcRepository,
            $segmenter,
            $pipeline,
        );

        return new FleetFiscalAggregator(
            $executor,
            $this->yearContext,
            $this->fiscalRules,
        );
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
}
