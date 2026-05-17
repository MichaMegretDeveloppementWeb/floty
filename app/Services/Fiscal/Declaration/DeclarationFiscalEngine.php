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
 * Orchestre le calcul fiscal d'une déclaration pour un couple
 * `(company, year)` en intégrant les décisions humaines de revue
 * (Phase 11 D5.2, refondu D5.8 avec breakdown par contrat).
 *
 * **Pourquoi un engine dédié** · le calcul d'une déclaration n'est pas
 * un calcul fiscal standard. Il applique en plus les décisions
 * « Requalified » prises par l'utilisateur sur les clusters LCD à
 * risque (D3-D4) en retirant l'exonération R-2024-021 aux contrats
 * concernés. La règle légale R-2024-021 reste pure (D5.1) ; les
 * opt-outs runtime sont portés par la **recette** (cet engine), pas
 * par la règle.
 *
 * **Pipeline d'exécution** ·
 *   1. Re-détection des clusters via {@see RiskDetectionService}
 *      (déterministe par fingerprint, doctrine D2).
 *   2. Lecture des décisions persistées (D3) ; match decisions ↔ clusters
 *      par `cluster_fingerprint` via {@see DeclarationDecisionResolver}.
 *   3. Pour chaque cluster matché en `Requalified` · extraction des
 *      `contractId` membres → opt-out runtime.
 *   4. Construction ad-hoc d'un {@see App\Services\Fiscal\FleetFiscalAggregator}
 *      via {@see DeclarationAggregatorFactory} (overlay R-YYYY-021
 *      avec décorateur WithOptOuts).
 *   5. Calcul de la taxe **par couple véhicule × entreprise** via
 *      `companyAnnualTaxBreakdownByVehicle`.
 *   6. **Refonte D5.8** · répartition proportionnelle au niveau contrat ·
 *      `taxe_contrat = (jours_contrat_année / jours_couple_année) × taxe_couple`
 *      (cohérent avec R-2024-002 prorata journalier). Enrichissement
 *      de chaque entry avec · cluster fingerprint/riskCode/decision,
 *      caractéristiques fiscales véhicule pré-formatées, flag opt-out.
 *   7. Tri global `(startDate, vehicleId, contractId)` ASC pour groupage
 *      visuel naturel côté frontend (les contrats consécutifs d'un
 *      cluster LCD sont adjacents → enroulables dans `<ClusterGroup>`).
 *   8. Composition du {@see FiscalDeclarationSnapshot} immuable.
 *
 * **No-regression invariant** · sans aucune décision `Requalified`, les
 * totaux du snapshot restent **strictement identiques** au calcul
 * standard via `FleetFiscalAggregator::companyAnnualTax()`. La
 * répartition par contrat ne crée pas de divergence d'arrondi visible
 * grâce à `assertEqualsWithDelta(0.001)` dans les tests.
 *
 * **SRP (Lot 4 D11 · F-19-004)** · les concerns « matching cluster ↔
 * décision » et « factory aggregator overlay » ont été extraits dans
 * deux services dédiés ({@see DeclarationDecisionResolver} et
 * {@see DeclarationAggregatorFactory}). Cet engine se concentre sur
 * l'orchestration et la composition du snapshot.
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

        // Phase D5.8.2 amélioration B · résout la traçabilité des
        // décisions « reprises auto par fingerprint » depuis le
        // prédécesseur de la déclaration courante. Permet au composant
        // frontend `<ClusterGroup>` d'afficher le badge 🔁 distinguant
        // décisions héritées vs décisions prises dans la session.
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

        // Phase 13 D5.10.N · l'ordre intermédiaire par vehicleId n'a
        // plus d'importance · le tri final est strictement
        // chronologique en bout de boucle (cf. usort plus bas).
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

            // Accumule les totaux **au niveau couple** (déjà arrondis
            // par `companyAnnualTaxBreakdownByVehicle`). Garantit
            // l'invariant · snapshot.totalDue == calcul standard, même
            // si la répartition par contrat introduit des écarts
            // d'arrondi d'un centime sur certaines lignes.
            $totalCo2Raw += $coupleCo2;
            $totalPollutantsRaw += $couplePollutants;

            // Phase 13 D5.10.R · 1ère passe · calcul des jours taxables
            // par contrat. Un contrat LCD bénéficie de l'exonération
            // R-2024-021 sauf s'il a été opt-out (Requalified) par une
            // décision de cluster. La taxe couple `$coupleCo2` est déjà
            // calculée avec R-2024-021 appliqué via le pipeline · elle
            // ne porte que sur les jours non-exonérés du couple. La
            // répartition par contrat doit donc se faire au prorata des
            // jours taxables, pas des jours bruts, sinon les contrats
            // LCD exemptés reçoivent illégitimement une part de taxe.
            // D5.15.5 · motifs d'exonération **véhicule-niveau** remontés
            // depuis le pipeline (électrique/hydrogène, handicap, OIG,
            // hybride conditionnel, activité spécifique, indispo
            // réductrice, etc.). Ces motifs s'appliquent à TOUS les
            // contrats du couple (entreprise, véhicule) puisqu'ils
            // découlent des caractéristiques du véhicule.
            //
            // On exclut R-{year}-021 (LCD courte durée) du jeu propagé ·
            // c'est une exonération **par contrat** (opt-out possible
            // par cluster), gérée séparément avec une wording dédiée
            // ci-dessous.
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

                // Compose la raison d'exonération à afficher (Show + PDF) ·
                // - tous les motifs véhicule-niveau du pipeline
                // - + le motif LCD (R-{year}-021) si ce contrat est LCD
                //   non opt-out
                // Séparateur · « · » (cohérent avec le reste de l'UI Floty).
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

        // Phase 13 D5.10.N · tri snapshot strictement chronologique
        // pour un breakdown plat lisible (plus de pseudo-groupes par
        // véhicule qui éparpillaient les clusters multi-véhicules).
        // Ordre stable · `(startDate, vehicleId, contractId)` ASC.
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

        // Lot 5 D15 · doctrine CIBS L. 131-1 + BOI-AIS-MOB-10-30-10
        // formalisée `project-management/taxes-rules/2025.md` § 4 ·
        // « le montant total à payer par chaque redevable est arrondi à
        // l'euro le plus proche, sans arrondi intermédiaire ».
        //
        // **Arrondi unique sur `totalDue`** (l'unique montant à
        // déclarer officiellement à l'administration). Les composantes
        // `co2DueTotal` et `pollutantsDueTotal` restent en haute
        // précision (centime) · elles sont des informations détaillées
        // affichées sur le PDF et la page Show pour traçabilité, pas
        // des montants déclaratifs en tant que tels.
        //
        // Invariant cross-engine · `totalDue` == `FleetFiscalAggregator::companyAnnualTax`
        // pour le même couple `(company, year)` quand aucune décision
        // de revue n'est appliquée · garantit qu'un brouillon
        // recalculé live (`engine->compute`) et un agrégat global
        // (`aggregator->companyAnnualTax` utilisé sur Companies Index)
        // donnent le même euro déclaré. Cf. test
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
     * Construit l'adresse postale formatée de l'entreprise utilisatrice
     * (Phase 13 D5.10.Y) à figer dans le snapshot pour le PDF. Concatène
     * les composants renseignés avec un saut de ligne entre ·
     *   - voie (line_1 + line_2 sur la même ligne s'ils sont tous deux
     *     renseignés)
     *   - localité (`{postal_code} {city}`)
     *   - pays (si différent de FR, pour éviter le bruit du cas par
     *     défaut)
     * Retourne null si aucune partie n'est renseignée.
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
     * Construit un résumé compact des caractéristiques fiscales du
     * véhicule (catégorie · méthode CO₂ · norme Euro). Affiché par
     * `<ContractRow>` côté frontend pour permettre à l'administration
     * de vérifier d'un coup d'œil la cohérence du montant calculé.
     *
     * Format · `M1 · WLTP 100 g · Euro 6` (3 segments séparés par
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

        $co2Method = match ($vfc->homologation_method) {
            HomologationMethod::Wltp => $vfc->co2_wltp !== null ? sprintf('WLTP %d g', $vfc->co2_wltp) : 'WLTP',
            HomologationMethod::Nedc => $vfc->co2_nedc !== null ? sprintf('NEDC %d g', $vfc->co2_nedc) : 'NEDC',
            HomologationMethod::Pa => $vfc->taxable_horsepower !== null ? sprintf('%d CV', $vfc->taxable_horsepower) : 'PA',
        };
        $segments[] = $co2Method;

        if ($vfc->euro_standard !== null) {
            // Phase 12 D5.9.B · libellé humain (« Euro 6 ») au lieu de
            // l'enum value brut (« euro_6 »).
            $segments[] = $vfc->euro_standard->label();
        }

        return implode(' · ', $segments);
    }
}
