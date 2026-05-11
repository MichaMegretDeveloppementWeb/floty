<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use App\Models\Contract;
use App\Models\FiscalReviewDecision;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
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
 * (Phase 11 D5.2, refondu D5.8 avec breakdown par contrat).
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
 *   5. Calcul de la taxe **par couple véhicule × entreprise** via
 *      `companyAnnualTaxBreakdownByVehicle`.
 *   6. **Refonte D5.8** · répartition proportionnelle au niveau contrat :
 *      `taxe_contrat = (jours_contrat_année / jours_couple_année) × taxe_couple`
 *      (cohérent avec R-2024-002 prorata journalier). Enrichissement
 *      de chaque entry avec : cluster fingerprint/riskCode/decision,
 *      caractéristiques fiscales véhicule pré-formatées, flag opt-out.
 *   7. Tri global `(vehicleId, startDate ASC)` pour groupage visuel
 *      naturel côté frontend (les contrats consécutifs d'un cluster
 *      LCD sont adjacents → enroulables dans `<ClusterGroup>`).
 *   8. Composition du {@see FiscalDeclarationSnapshot} immuable.
 *
 * **No-regression invariant** : sans aucune décision `Requalified`, les
 * totaux du snapshot restent **strictement identiques** au calcul
 * standard via `FleetFiscalAggregator::companyAnnualTax()`. La
 * répartition par contrat ne crée pas de divergence d'arrondi visible
 * grâce à `assertEqualsWithDelta(0.001)` dans les tests.
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
        private FiscalDeclarationReadRepositoryInterface $declarations,
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

        // Phase D5.8.2 amélioration B · résout la traçabilité des
        // décisions « reprises auto par fingerprint » depuis le
        // prédécesseur de la déclaration courante. Permet au composant
        // frontend `<ClusterGroup>` d'afficher le badge 🔁 distinguant
        // décisions héritées vs décisions prises dans la session.
        $retainedFromByFingerprint = $this->resolveRetainedFromMap(
            $companyId,
            $year,
            $persistedDecisions,
        );

        $contractClusterMap = $this->buildContractClusterMap(
            $clusters,
            $appliedDecisions,
            $retainedFromByFingerprint,
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
                contractBreakdown: [],
                appliedDecisions: $appliedDecisions,
                optOutContractIds: $optOutContractIds,
            );
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        $aggregator = $this->buildAdHocAggregator($optOutContractIds);
        $rowsByVehicle = $aggregator->companyAnnualTaxBreakdownByVehicle(
            $companyId,
            $vehiclesById,
            $contractsByPair,
            $unavailabilitiesByVehicleId,
            $year,
        );

        // Tri stable par vehicleId pour reproductibilité (la boucle
        // interne trie ensuite chronologiquement par startDate).
        usort(
            $rowsByVehicle,
            static fn (array $a, array $b): int => $a['vehicleId'] <=> $b['vehicleId'],
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

            $coupleTotalDays = array_sum(array_column($contractsWithDays, 'days'));
            $coupleCo2 = (float) $row['taxCo2'];
            $couplePollutants = (float) $row['taxPollutants'];

            // Accumule les totaux **au niveau couple** (déjà arrondis
            // par `companyAnnualTaxBreakdownByVehicle`). Garantit
            // l'invariant : snapshot.totalDue == calcul standard, même
            // si la répartition par contrat introduit des écarts
            // d'arrondi d'un centime sur certaines lignes.
            $totalCo2Raw += $coupleCo2;
            $totalPollutantsRaw += $couplePollutants;

            foreach ($contractsWithDays as $entry) {
                /** @var Contract $contract */
                $contract = $entry['contract'];
                $daysInYear = $entry['days'];

                $share = $coupleTotalDays > 0 ? $daysInYear / $coupleTotalDays : 0.0;
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
                );
            }
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
            contractBreakdown: $contractEntries,
            appliedDecisions: $appliedDecisions,
            optOutContractIds: $optOutContractIds,
        );
    }

    /**
     * Trie les contrats d'un véhicule par `startDate ASC` et calcule
     * leur durée dans l'année cible. Le tri par date garantit le
     * groupage visuel naturel des clusters LCD côté frontend (les
     * contrats consécutifs d'un cluster sont adjacents).
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
                'days' => count($contract->expandToDaysInYear($year)),
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

    /**
     * Construit la map `contractId => clusterInfo` pour enrichir
     * chaque {@see ContractSnapshotEntry} avec son cluster
     * d'appartenance + décision persistée si présente + flag
     * `retainedFrom` (D5.8.2 amélioration B audit).
     *
     * @param  list<ReviewClusterData>  $clusters
     * @param  list<AppliedDecisionEntry>  $appliedDecisions
     * @param  array<string, int>  $retainedFromByFingerprint  Map fingerprint → id de la déclaration prédécesseur d'où la décision a été reprise
     * @return array<int, array{fingerprint: string, riskCode: RiskCode, riskLevel: RiskLevel, decision: ?ReviewDecisionType, justification: ?string, retainedFrom: ?int}>
     */
    private function buildContractClusterMap(
        array $clusters,
        array $appliedDecisions,
        array $retainedFromByFingerprint,
    ): array {
        $decisionsByFingerprint = [];
        foreach ($appliedDecisions as $applied) {
            $decisionsByFingerprint[$applied->clusterFingerprint] = $applied;
        }

        $map = [];
        foreach ($clusters as $cluster) {
            $applied = $decisionsByFingerprint[$cluster->fingerprint] ?? null;
            $retainedFrom = $retainedFromByFingerprint[$cluster->fingerprint] ?? null;
            foreach ($cluster->contracts as $clusterContract) {
                $map[$clusterContract->contractId] = [
                    'fingerprint' => $cluster->fingerprint,
                    'riskCode' => $cluster->code,
                    'riskLevel' => $cluster->level,
                    'decision' => $applied?->decision,
                    'justification' => $applied?->justification,
                    'retainedFrom' => $retainedFrom,
                ];
            }
        }

        return $map;
    }

    /**
     * Résout les décisions « reprises auto par fingerprint » du
     * prédécesseur (Phase 11 D5.8.2 amélioration B audit).
     *
     * Une décision est dite « reprise » si la déclaration courante du
     * couple `(company, year)` a un prédécesseur (= régénération en
     * cours ou achevée) ET que la décision matchée par fingerprint a
     * été persistée **avant** la création de la déclaration courante
     * (`decided_at < currentDeclaration.created_at`).
     *
     * Retourne une map `fingerprint => predecessorDeclarationId` qui
     * permet au frontend d'afficher le badge `🔁 Décision reprise`.
     * Map vide si la déclaration courante n'est pas une régénération
     * (= première préparation) ou si aucune décision n'a été
     * persistée avant la session courante.
     *
     * @param  iterable<FiscalReviewDecision>  $persistedDecisions
     * @return array<string, int>
     */
    private function resolveRetainedFromMap(
        int $companyId,
        int $year,
        iterable $persistedDecisions,
    ): array {
        $current = $this->declarations->findCurrentForCompanyYear($companyId, $year);
        if ($current === null) {
            return [];
        }

        $predecessor = $this->declarations->findPredecessorOf($current->id);
        if ($predecessor === null) {
            return [];
        }

        $currentCreatedAt = $current->created_at;
        if ($currentCreatedAt === null) {
            return [];
        }

        $retainedMap = [];
        foreach ($persistedDecisions as $decision) {
            if ($decision->decided_at !== null && $decision->decided_at->lessThan($currentCreatedAt)) {
                $retainedMap[$decision->cluster_fingerprint] = $predecessor->id;
            }
        }

        return $retainedMap;
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

    /**
     * Construit un résumé compact des caractéristiques fiscales du
     * véhicule (catégorie · méthode CO₂ · norme Euro). Affiché par
     * `<ContractRow>` côté frontend pour permettre à l'administration
     * de vérifier d'un coup d'œil la cohérence du montant calculé.
     *
     * Format : `M1 · WLTP 100 g · Euro 6` (3 segments séparés par
     * point médian). Adapte aux méthodes NEDC/PA (puissance fiscale)
     * et aux véhicules sans VFC active (placeholder « VFC absente »).
     */
    private function buildVehicleFiscalSummary(Vehicle $vehicle): string
    {
        $vfc = $vehicle->fiscalCharacteristics->first();
        if (! $vfc instanceof VehicleFiscalCharacteristics) {
            return 'VFC absente';
        }

        $segments = [$vfc->reception_category->value];

        $co2Method = match ($vfc->homologation_method->value) {
            'wltp' => $vfc->co2_wltp !== null ? sprintf('WLTP %d g', $vfc->co2_wltp) : 'WLTP',
            'nedc' => $vfc->co2_nedc !== null ? sprintf('NEDC %d g', $vfc->co2_nedc) : 'NEDC',
            'pa' => $vfc->taxable_horsepower !== null ? sprintf('%d CV', $vfc->taxable_horsepower) : 'PA',
            default => $vfc->homologation_method->value,
        };
        $segments[] = $co2Method;

        if ($vfc->euro_standard !== null) {
            $segments[] = $vfc->euro_standard->value;
        }

        return implode(' · ', $segments);
    }
}
