<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\ContractTaxYearBreakdownData;
use App\Data\User\Contract\ContractTaxYearSegmentBreakdownData;
use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\Data\User\Vehicle\VehicleFullYearTaxSegmentData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\PipelineResult;
use App\Fiscal\ValueObjects\AppliedExemption;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\FiscalRule\FiscalRuleQueryService;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Collection;

/**
 * Agrégateur fiscal annuel à l'échelle de la flotte · cœur fiscal CIBS Floty
 * (taxe CO₂ + taxe polluants).
 *
 * Centralise les sommations de taxe (par véhicule, par entreprise, par
 * flotte) qui étaient dupliquées dans 4 controllers (Vehicle, Company,
 * Dashboard, Planning).
 *
 * Source de vérité des calculs fiscaux (cf. ADR-0022 · classes PHP source
 * de vérité · ADR-0006 · moteur fiscal V1).
 *
 * **Note R-2024-003** : l'arrondi half-up au centime (`round(.., 2, ..)`)
 * est appliqué **une seule fois par redevable** (entreprise utilisatrice),
 * jamais par couple intermédiaire. L'aggregator somme les `*DueRaw` des
 * `PipelineResult` et arrondit en sortie. Cf. ADR-0006 § 2.
 *
 * **Refonte 04.F (ADR-0014)** : passe de `AnnualCumulByPair` à
 * `ContractsByPair`. Les indispos par véhicule sont passées séparément
 * (map `vehicleId → list<Unavailability>`) pour alimenter R-2024-008.
 *
 * --------------------------------------------------------------------------
 * DOCTRINE V1 · NON REFONDU SOUS DOCTRINE ZÉRO-DETTE (Lot 4 D10 · F-17-001)
 * --------------------------------------------------------------------------
 *
 * Ce service fait ~620 lignes (dont ~239 lignes de commentaires explicatifs
 * · rappels BOFiP, bases légales CIBS, exemples chiffrés · ~38 % de
 * commentaires). L'audit production-ready 2026-05-11 (F-17-001) le marque
 * comme cible SRP > 500 l.
 *
 * **Décision user explicite (plan-remediation Vague 1 Lot 4 D10 ·
 * 2026-05-13) · NON refondu.**
 *
 * Justifications ·
 *  - Cœur fiscal CIBS · refonte mal faite = risque catastrophique
 *    (taxe fausse en prod, conformité réglementaire LFi 2024 art. 14)
 *  - ~38 % des lignes sont des commentaires explicatifs (lisibilité,
 *    audit BOFiP, traçabilité réglementaire)
 *  - Coût refonte (refonte + tests d'équivalence ≥10 fixtures + Chrome
 *    live exhaustif) disproportionné vs gain maintenabilité
 *  - ADR-0022 désigne ce service comme « source de vérité » fiscale ·
 *    préservation prudente prime sur SRP cosmétique
 *
 * Conditions de réouverture du dossier (V2+) ·
 *  - Si une nouvelle règle fiscale doit être intégrée et que le code
 *    devient illisible, refondre AVEC tests d'équivalence ironclad
 *  - Si une dépendance externe change de signature, opportunité de
 *    découper la zone touchée
 *  - Si l'overhead cognitif empêche un dev junior de comprendre,
 *    réévaluer
 *
 * **Toute modification ici doit ·**
 *  1. Avoir un test d'équivalence préalable sur ≥3 fixtures représentatives
 *  2. Être validée Chrome live sur le moteur de génération de déclaration
 *  3. Spot-check croisé sur ≥2 calculs CIBS pré-existants
 *
 * Filet de sécurité existant · `FiscalCalculatorTest`,
 * `FiscalAdditivityTest`, `FiscalInvariantsTest`, `MultiYearContractTest`,
 * `MultiVfcEdgeCasesTest`. Tout changement doit y rester vert.
 */
final class FleetFiscalAggregator
{
    /**
     * Cache mémoire intra-instance des projections DTO des règles
     * fiscales indexé par `"{year}|{sortedCodes}"`. Évite la
     * re-construction répétée quand l'aggregator est réutilisé sur
     * plusieurs véhicules / contrats à l'intérieur d'une même
     * requête. Phase 13 D5.14 · les DTOs viennent désormais du
     * registry (classes PHP), plus de lecture BDD.
     *
     * @var array<string, list<FiscalRuleListItemData>>
     */
    private array $rulesCache = [];

    /**
     * Cache mémoire intra-instance des `PipelineResult` du « taxe pleine
     * année théorique » indexé par `"{vehicleId}|{year}"`. Le résultat
     * dépend exclusivement du véhicule et de l'année (contrat full-year
     * synthétique, indispos vides), il est donc partageable entre
     * `vehicleFullYearTax` et `vehicleFullYearTaxBreakdown` - la liste
     * Flotte gagne ~50 % de pipeline runs.
     *
     * @var array<string, PipelineResult>
     */
    private array $fullYearResultCache = [];

    /**
     * Cache mémoire intra-instance des DTOs `VehicleFullYearTaxBreakdownData`
     * indexé par `"{vehicleId}|{year}"`. Le DTO est purement déterministe
     * sur `(vehicle, year)` (contrat synthétique full-year, indispos vides) ·
     * il est donc safe à mémoïser pour la durée de vie du Service Laravel
     * (resolved per-request).
     *
     * Hot paths bénéficiaires (Lot 3 D05) ·
     * - `VehicleListingService::listForOptions` · boucle vehicles × availableYears
     * - `VehicleListingService::listPaginated` · N véhicules paginés × année courante
     * - `PlanningHeatmapService::buildHeatmap[ForCompany]` · N véhicules × année
     *
     * @var array<string, VehicleFullYearTaxBreakdownData>
     */
    private array $fullYearBreakdownCache = [];

    /**
     * Cache mémoire intra-instance des **agrégats par couple** (S1.2 du
     * plan optim perf 2026-05-15 · cause C-2 · les méthodes "agrégat
     * par couple" exécutaient le pipeline sans mémoïsation).
     *
     * **Clés scalaires uniquement** · les autres args (Collection vehicles,
     * ContractsByPair, indispos) viennent toujours du même
     * `DashboardScopeContext` / `CompanyDetailScope` pour un
     * `(companyId, year)` ou `(vehicleId, year)` donné dans une requête.
     * Pas de pattern « 2 sous-ensembles différents pour la même clé »
     * dans le code (audit a vérifié).
     *
     * **Sécurité** · `ContractsByPair` est `final readonly` (immutable),
     * pas de mutation entre 2 appels. `Collection<Vehicle>` est passée
     * par valeur conceptuellement (pas mutée par les méthodes).
     *
     * @var array<string, float>
     */
    private array $vehicleAnnualTaxCache = [];

    /** @var array<string, float> */
    private array $companyAnnualTaxCache = [];

    /** @var array<string, list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>> */
    private array $vehicleAnnualTaxBreakdownByCompanyCache = [];

    /** @var array<string, list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>> */
    private array $companyAnnualTaxBreakdownByVehicleCache = [];

    /** @var array<string, float> */
    private array $fleetAnnualTaxCache = [];

    public function __construct(
        private readonly FiscalSegmentedExecutor $pipeline,
        private readonly FiscalYearContext $yearContext,
        private readonly FiscalRuleQueryService $rulesQuery,
    ) {}

    /**
     * Total fiscal annuel d'un véhicule sommé sur toutes les
     * entreprises auxquelles il a été attribué.
     *
     * Le véhicule doit avoir ses `fiscalCharacteristics` actives
     * pré-chargées (sinon le pipeline déclenche une nouvelle requête
     * par appel via le repository).
     *
     * @param  list<Unavailability>  $vehicleUnavailabilities  Indispos du véhicule sur l'année
     */
    public function vehicleAnnualTax(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleUnavailabilities,
        int $year,
    ): float {
        $key = $vehicle->id.'|'.$year;

        return $this->vehicleAnnualTaxCache[$key] ??= $this->computeVehicleAnnualTax(
            $vehicle,
            $contracts,
            $vehicleUnavailabilities,
            $year,
        );
    }

    /**
     * @param  list<Unavailability>  $vehicleUnavailabilities
     */
    private function computeVehicleAnnualTax(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleUnavailabilities,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($contracts->pairsForVehicle($vehicle->id) as $pairContracts) {
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $pairContracts, $vehicleUnavailabilities, $year),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Total fiscal annuel d'une entreprise sommé sur tous les
     * véhicules qu'elle a utilisés. **Implémente R-2024-003** : un
     * seul arrondi par redevable.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     */
    public function companyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): float {
        $key = $companyId.'|'.$year;

        return $this->companyAnnualTaxCache[$key] ??= $this->computeCompanyAnnualTax(
            $companyId,
            $vehiclesById,
            $contracts,
            $unavailabilitiesByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     */
    private function computeCompanyAnnualTax(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($contracts->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $result = $this->pipeline->execute(
                $this->buildContext(
                    $vehicle,
                    $pairContracts,
                    $unavailabilitiesByVehicleId[$vehicleId] ?? [],
                    $year,
                ),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * **Taxe pleine année théorique** d'un véhicule : ce qu'il
     * coûterait s'il était attribué 100 % du temps à une seule
     * entreprise (sans LCD, sans indispo, prorata = 1.0).
     *
     * Construit un contrat synthétique non-persisté (1er jan → 31 déc)
     * pour passer le pipeline normalement. Ce contrat est par
     * construction non LCD (durée > 30 j et pas un mois civil entier)
     * et sans indispo, donc R-2024-021 et R-2024-008 ne retirent rien
     * du numérateur ; R-2024-002 calcule prorata = daysInYear / daysInYear = 1.0.
     */
    public function vehicleFullYearTax(Vehicle $vehicle, int $year): float
    {
        $result = $this->fullYearPipelineResult($vehicle, $year);

        return round($result->co2DueRaw + $result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Détail complet du calcul de la taxe pleine année d'un véhicule -
     * affiché dans la sidebar de la page Show pour expliquer comment
     * le total a été obtenu (méthode CO₂, catégorie polluants,
     * exonérations appliquées, codes règles).
     *
     * Mémoïsé per-request via `$fullYearBreakdownCache` (Lot 3 D05) · les
     * boucles N véhicules × M années (Index Flotte, sélecteur véhicule,
     * heatmap planning) ne paient le pipeline qu'une fois par couple.
     */
    public function vehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $key = $vehicle->id.'|'.$year;

        return $this->fullYearBreakdownCache[$key] ??= $this->computeVehicleFullYearTaxBreakdown($vehicle, $year);
    }

    private function computeVehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        // On exécute le pipeline avec un contrat synthétique full-year
        // (1ᵉʳ jan → 31 déc) pour calculer le taxe pleine. L'orchestrateur
        // segmente automatiquement par VFC : 1 breakdown en mono-VFC,
        // N en multi-VFC.
        $context = $this->buildContext(
            $vehicle,
            [$this->fullYearSyntheticContract($year)],
            [],
            $year,
        );
        $breakdowns = $this->pipeline->executeWithSegments($context);

        $taxSegments = [];
        $totalRaw = 0.0;
        /** @var array<string, AppliedExemptionData> $exemptionsByCode */
        $exemptionsByCode = [];
        /** @var array<string, true> $ruleCodesSet */
        $ruleCodesSet = [];

        foreach ($breakdowns as $breakdown) {
            $vfc = $breakdown->vfcSegment->vfc;
            $result = $breakdown->result;

            $co2Tariff = round($result->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
            $pollutantsTariff = round($result->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
            $co2Due = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $pollutantsDue = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $taxSegments[] = new VehicleFullYearTaxSegmentData(
                effectiveFromInYear: $breakdown->start->toDateString(),
                effectiveToInYear: $breakdown->end->toDateString(),
                daysInSegment: $breakdown->days(),
                vfc: VehicleFiscalCharacteristicsData::fromModel($vfc),
                co2Method: $result->co2Method,
                co2FullYearTariff: $co2Tariff,
                co2Explanation: $this->buildCo2Explanation($vfc, $result->co2Method, $co2Tariff, $year),
                co2Due: $co2Due,
                pollutantCategory: $result->pollutantCategory,
                pollutantsFullYearTariff: $pollutantsTariff,
                pollutantsExplanation: $this->buildPollutantsExplanation($vfc, $result->pollutantCategory, $pollutantsTariff),
                pollutantsDue: $pollutantsDue,
                appliedExemptions: array_map(
                    static fn ($e) => AppliedExemptionData::fromValueObject($e),
                    $result->appliedExemptions,
                ),
                appliedRuleCodes: $result->appliedRuleCodes,
            );

            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
            foreach ($result->appliedExemptions as $exemption) {
                $exemptionsByCode[$exemption->ruleCode] ??= AppliedExemptionData::fromValueObject($exemption);
            }
            foreach ($result->appliedRuleCodes as $code) {
                $ruleCodesSet[$code] = true;
            }
        }

        $appliedRuleCodes = array_keys($ruleCodesSet);
        $appliedRules = $this->loadRulesByCodes($year, $appliedRuleCodes);

        return new VehicleFullYearTaxBreakdownData(
            daysInYear: $this->yearContext->daysInYear($year),
            total: round($totalRaw, 2, PHP_ROUND_HALF_UP),
            appliedExemptions: array_values($exemptionsByCode),
            appliedRuleCodes: $appliedRuleCodes,
            appliedRules: $appliedRules,
            taxSegments: $taxSegments,
        );
    }

    /**
     * Détail fiscal complet d'un contrat - affiché dans la section
     * « Taxes générées » de la page Show contrat.
     *
     * Le pipeline tourne par année. Si le contrat chevauche 2 années
     * civiles (ex. 1er nov 2024 → 31 jan 2025), on exécute le pipeline
     * deux fois et on agrège.
     *
     * **Granularité par fenêtre (chantier Φ.bis)** · utilise
     * `executeWithSegments()` pour exposer chaque sous-période VFC ×
     * Règles dans le DTO. Indispensable quand un barème change en cours
     * d'année (ex. polluants 2026 +30 % au 01/03, LF 2026 art. 58 V IV)
     * ou quand la VFC évolue. L'agrégation au niveau année reste fournie
     * dans les champs flat pour le résumé de tête.
     *
     * Le `$contract->vehicle->fiscalCharacteristics` doit être eager-loadé
     * par l'appelant (cf. `ContractReadRepository::findByIdWithRelations`).
     *
     * @param  list<Unavailability>  $vehicleUnavailabilities
     */
    public function contractTaxBreakdown(
        Contract $contract,
        array $vehicleUnavailabilities,
    ): ContractTaxBreakdownData {
        $vehicle = $contract->vehicle;
        $startYear = $contract->start_date->year;
        $endYear = $contract->end_date->year;

        $years = [];
        $totalRaw = 0.0;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $breakdowns = $this->pipeline->executeWithSegments(
                $this->buildContext($vehicle, [$contract], $vehicleUnavailabilities, $year),
            );

            $daysInContractInYear = $contract->countDaysInYear($year);

            $segmentsDto = [];
            $co2RawYear = 0.0;
            $pollutantsRawYear = 0.0;
            $daysAssignedYear = 0;
            /** @var array<string, AppliedExemption> $exemptionsByCode */
            $exemptionsByCode = [];
            /** @var array<string, true> $ruleCodesSet */
            $ruleCodesSet = [];

            foreach ($breakdowns as $b) {
                $r = $b->result;

                // Fenêtre sans jour-contrat assigné · entièrement ignorée
                // pour ce contrat (ex. segment 01/09-31/12 d'une scission
                // Ordo 2025-1247 alors que le contrat se termine en avril).
                // Les règles actives uniquement sur cette fenêtre ne sont
                // pas « appliquées » au contrat · elles n'auraient
                // contribué que si le contrat avait débordé.
                if ($r->daysAssigned === 0) {
                    continue;
                }

                $segCo2Tariff = round($r->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
                $segPollutantsTariff = round($r->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
                $segCo2Due = round($r->co2DueRaw, 2, PHP_ROUND_HALF_UP);
                $segPollutantsDue = round($r->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

                $segmentsDto[] = new ContractTaxYearSegmentBreakdownData(
                    effectiveFromInYear: $b->start->toDateString(),
                    effectiveToInYear: $b->end->toDateString(),
                    daysAssignedToContract: $r->daysAssigned,
                    daysInYear: $r->daysInYear,
                    co2Method: $r->co2Method,
                    pollutantCategory: $r->pollutantCategory,
                    co2FullYearTariff: $segCo2Tariff,
                    pollutantsFullYearTariff: $segPollutantsTariff,
                    co2Due: $segCo2Due,
                    pollutantsDue: $segPollutantsDue,
                    appliedExemptions: array_map(
                        static fn ($e) => AppliedExemptionData::fromValueObject($e),
                        $r->appliedExemptions,
                    ),
                    appliedRuleCodes: $r->appliedRuleCodes,
                );

                $co2RawYear += $r->co2DueRaw;
                $pollutantsRawYear += $r->pollutantsDueRaw;
                $daysAssignedYear += $r->daysAssigned;
                foreach ($r->appliedExemptions as $exemption) {
                    $exemptionsByCode[$exemption->ruleCode] ??= $exemption;
                }
                foreach ($r->appliedRuleCodes as $code) {
                    $ruleCodesSet[$code] = true;
                }
            }

            // Résumé année · agrégation des fenêtres non-vides. Le tarif
            // affiché en tête est celui de la première fenêtre **utile**
            // (avec jours-contrat assignés), pour éviter de tromper l'UI
            // avec un tarif pré-scission alors que le contrat débute
            // après la scission. L'UI doit afficher la scission via
            // `segments` si len(segments) > 1.
            $leaderResult = null;
            foreach ($breakdowns as $b) {
                if ($b->result->daysAssigned > 0) {
                    $leaderResult = $b->result;
                    break;
                }
            }
            // Cas dégénéré · aucun jour-contrat assigné (LCD pur,
            // exemption totale, contrat hors year span effectif). On
            // retombe sur le premier segment pour conserver des valeurs
            // cohérentes (method, category, tariffs structurels).
            $firstResult = $leaderResult ?? $breakdowns[0]->result;
            $co2Tariff = round($firstResult->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
            $pollutantsTariff = round($firstResult->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
            $co2Due = round($co2RawYear, 2, PHP_ROUND_HALF_UP);
            $pollutantsDue = round($pollutantsRawYear, 2, PHP_ROUND_HALF_UP);
            $yearTotalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

            $appliedRuleCodes = array_keys($ruleCodesSet);
            $appliedRules = $this->loadRulesByCodes($year, $appliedRuleCodes);

            // D5.10.T · montant hypothétique « si pas LCD » · seulement
            // pour les contrats effectivement exonérés par R-2024-021.
            // Calcul direct (tariff × prorata jours-contrat / jours-année)
            // sans 2e passe pipeline · l'approximation suffit comme aide
            // à la décision de requalification cluster, le calcul exact
            // étant fait par `DeclarationFiscalEngine` lors de la revue.
            $lcdExempted = in_array('R-2024-021', $appliedRuleCodes, true);
            $hypoCo2 = null;
            $hypoPollutants = null;
            $hypoTotal = null;
            if ($lcdExempted && $firstResult->daysInYear > 0) {
                $proratio = $daysInContractInYear / $firstResult->daysInYear;
                $hypoCo2 = round($co2Tariff * $proratio, 2, PHP_ROUND_HALF_UP);
                $hypoPollutants = round($pollutantsTariff * $proratio, 2, PHP_ROUND_HALF_UP);
                $hypoTotal = round($hypoCo2 + $hypoPollutants, 2, PHP_ROUND_HALF_UP);
            }

            $years[] = new ContractTaxYearBreakdownData(
                year: $year,
                daysInContractInYear: $daysInContractInYear,
                daysAssigned: $daysAssignedYear,
                daysInYear: $firstResult->daysInYear,
                co2Method: $firstResult->co2Method,
                pollutantCategory: $firstResult->pollutantCategory,
                co2FullYearTariff: $co2Tariff,
                pollutantsFullYearTariff: $pollutantsTariff,
                co2Due: $co2Due,
                pollutantsDue: $pollutantsDue,
                totalDue: $yearTotalDue,
                appliedExemptions: array_map(
                    static fn ($e) => AppliedExemptionData::fromValueObject($e),
                    array_values($exemptionsByCode),
                ),
                appliedRuleCodes: $appliedRuleCodes,
                appliedRules: $appliedRules,
                segments: $segmentsDto,
                hypotheticalCo2DueIfNoLcd: $hypoCo2,
                hypotheticalPollutantsDueIfNoLcd: $hypoPollutants,
                hypotheticalTotalDueIfNoLcd: $hypoTotal,
            );

            $totalRaw += $co2RawYear + $pollutantsRawYear;
        }

        return new ContractTaxBreakdownData(
            years: $years,
            totalDue: round($totalRaw, 2, PHP_ROUND_HALF_UP),
        );
    }

    private function buildCo2Explanation(
        ?VehicleFiscalCharacteristics $vfc,
        HomologationMethod $method,
        float $tariff,
        int $year,
    ): string {
        if ($vfc === null) {
            return 'Tarif annuel CO₂ calculé sans caractéristiques fiscales actives.';
        }

        $value = match ($method) {
            HomologationMethod::Wltp => $vfc->co2_wltp !== null ? "{$vfc->co2_wltp} g/km (WLTP)" : 'WLTP',
            HomologationMethod::Nedc => $vfc->co2_nedc !== null ? "{$vfc->co2_nedc} g/km (NEDC)" : 'NEDC',
            HomologationMethod::Pa => $vfc->taxable_horsepower !== null ? "{$vfc->taxable_horsepower} CV (puissance administrative)" : 'PA',
        };

        // Tarif à 0 € → exonération applicable. L'utilisateur a la
        // liste des motifs dans la section « Exonérations applicables »
        // juste en-dessous, on évite donc de re-dérouler le calcul
        // (qui serait trompeur : « ... → 0 € » sans contexte).
        if ($tariff === 0.0) {
            return sprintf(
                '%s - exonérée pour ce véhicule (voir motif ci-dessous).',
                $value,
            );
        }

        return sprintf(
            '%s × barème CO₂ %d → tarif annuel %s.',
            $value,
            $year,
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    private function buildPollutantsExplanation(
        ?VehicleFiscalCharacteristics $vfc,
        PollutantCategory $category,
        float $tariff,
    ): string {
        if ($vfc === null) {
            return 'Tarif polluants calculé sans caractéristiques fiscales actives.';
        }

        $energy = $vfc->energy_source->label();
        $euro = $vfc->euro_standard?->label() ?? 'sans norme Euro renseignée';

        if ($tariff === 0.0) {
            return sprintf(
                '%s · %s → exonérée pour ce véhicule (voir motif ci-dessous).',
                $energy,
                $euro,
            );
        }

        return sprintf(
            '%s · %s → catégorie %s → tarif fixe annuel %s.',
            $energy,
            $euro,
            $category->label(),
            number_format($tariff, 2, ',', ' ').' €',
        );
    }

    /**
     * Détail du coût annuel d'un véhicule réparti par entreprise
     * utilisatrice avec **séparation CO₂ / polluants / total**. Une
     * entrée par entreprise effectivement attributaire, non triée
     * (tri par jours décroissants à la charge du consommateur).
     *
     * @param  list<Unavailability>  $vehicleUnavailabilities
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    public function vehicleAnnualTaxBreakdownByCompany(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleUnavailabilities,
        int $year,
    ): array {
        $key = $vehicle->id.'|'.$year;

        return $this->vehicleAnnualTaxBreakdownByCompanyCache[$key] ??= $this->computeVehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $contracts,
            $vehicleUnavailabilities,
            $year,
        );
    }

    /**
     * @param  list<Unavailability>  $vehicleUnavailabilities
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    private function computeVehicleAnnualTaxBreakdownByCompany(
        Vehicle $vehicle,
        ContractsByPair $contracts,
        array $vehicleUnavailabilities,
        int $year,
    ): array {
        $rows = [];
        foreach ($contracts->pairsForVehicle($vehicle->id) as $companyId => $pairContracts) {
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, $pairContracts, $vehicleUnavailabilities, $year),
            );

            $taxCo2 = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $taxPollutants = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $rows[] = [
                'companyId' => $companyId,
                'days' => $result->daysAssigned,
                'taxCo2' => $taxCo2,
                'taxPollutants' => $taxPollutants,
                'taxTotal' => round($taxCo2 + $taxPollutants, 2, PHP_ROUND_HALF_UP),
            ];
        }

        return $rows;
    }

    /**
     * Miroir de `vehicleAnnualTaxBreakdownByCompany` côté entreprise :
     * détail fiscal d'une entreprise réparti **par véhicule utilisé**
     * sur l'année (chantier N.2). Utilisé par l'onglet Fiscalité de la
     * fiche Company Show et par `DeclarationFiscalEngine` pour
     * composer le snapshot de déclaration.
     *
     * **`appliedExemptions`** propagés depuis le résultat pipeline ·
     * permet au moteur de déclaration de matérialiser le motif
     * d'exonération sur chaque ligne contrat (au-delà du seul R-021
     * historiquement hardcodé).
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     * @return list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>
     */
    public function companyAnnualTaxBreakdownByVehicle(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): array {
        $key = $companyId.'|'.$year;

        return $this->companyAnnualTaxBreakdownByVehicleCache[$key] ??= $this->computeCompanyAnnualTaxBreakdownByVehicle(
            $companyId,
            $vehiclesById,
            $contracts,
            $unavailabilitiesByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     * @return list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float, appliedExemptions: list<AppliedExemption>}>
     */
    private function computeCompanyAnnualTaxBreakdownByVehicle(
        int $companyId,
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): array {
        $rows = [];
        foreach ($contracts->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }

            $result = $this->pipeline->execute(
                $this->buildContext(
                    $vehicle,
                    $pairContracts,
                    $unavailabilitiesByVehicleId[$vehicleId] ?? [],
                    $year,
                ),
            );

            $taxCo2 = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $taxPollutants = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);

            $rows[] = [
                'vehicleId' => $vehicleId,
                'days' => $result->daysAssigned,
                'taxCo2' => $taxCo2,
                'taxPollutants' => $taxPollutants,
                'taxTotal' => round($taxCo2 + $taxPollutants, 2, PHP_ROUND_HALF_UP),
                'appliedExemptions' => $result->appliedExemptions,
            ];
        }

        return $rows;
    }

    /**
     * Total fiscal annuel sommé sur toute la flotte (tous couples
     * véhicule × entreprise confondus). Affiché côté Dashboard ;
     * agrégat informatif (pas un montant déclaratif).
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     */
    public function fleetAnnualTax(
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): float {
        $key = (string) $year;

        return $this->fleetAnnualTaxCache[$key] ??= $this->computeFleetAnnualTax(
            $vehiclesById,
            $contracts,
            $unavailabilitiesByVehicleId,
            $year,
        );
    }

    /**
     * @param  Collection<int, Vehicle>  $vehiclesById
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     */
    private function computeFleetAnnualTax(
        Collection $vehiclesById,
        ContractsByPair $contracts,
        array $unavailabilitiesByVehicleId,
        int $year,
    ): float {
        $totalRaw = 0.0;
        foreach ($contracts->vehicleCompanyPairs() as $pair) {
            $vehicle = $vehiclesById->get($pair['vehicleId']);
            if ($vehicle === null) {
                continue;
            }
            $result = $this->pipeline->execute(
                $this->buildContext(
                    $vehicle,
                    $pair['contracts'],
                    $unavailabilitiesByVehicleId[$pair['vehicleId']] ?? [],
                    $year,
                ),
            );
            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
        }

        return round($totalRaw, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  list<Contract>  $contractsForPair
     * @param  list<Unavailability>  $vehicleUnavailabilities
     */
    private function buildContext(
        Vehicle $vehicle,
        array $contractsForPair,
        array $vehicleUnavailabilities,
        int $year,
    ): PipelineContext {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $year,
            daysInYear: $this->yearContext->daysInYear($year),
            contractsForPair: $contractsForPair,
            vehicleUnavailabilitiesInYear: $vehicleUnavailabilities,
        );
    }

    /**
     * Mémoïsation du chargement des règles fiscales par codes pour une
     * année - clé `"{year}|{sortedCodes}"` afin que des appels avec un
     * ordre de codes différent (mais même contenu) partagent l'entrée.
     *
     * Phase 13 D5.14 · les DTOs viennent désormais du registry (classes
     * PHP), plus de lecture BDD. Le cache stocke des DTOs directement,
     * plus de transformation `fromModel` côté caller.
     *
     * @param  list<string>  $codes
     * @return list<FiscalRuleListItemData>
     */
    private function loadRulesByCodes(int $year, array $codes): array
    {
        sort($codes);
        $key = $year.'|'.implode(',', $codes);

        return $this->rulesCache[$key] ??= $this->rulesQuery->listByCodesForYear($year, $codes);
    }

    /**
     * Mémoïsation du `PipelineResult` du calcul plein année théorique
     * d'un véhicule - purement fonction de `(vehicleId, year)` (contrat
     * synthétique full-year, indispos vides).
     */
    private function fullYearPipelineResult(Vehicle $vehicle, int $year): PipelineResult
    {
        $key = $vehicle->id.'|'.$year;

        return $this->fullYearResultCache[$key] ??= $this->pipeline->execute(
            $this->buildContext($vehicle, [$this->fullYearSyntheticContract($year)], [], $year),
        );
    }

    /**
     * Contrat synthétique non-persisté couvrant toute l'année (1er jan
     * → 31 déc), utilisé pour calculer le taxe pleine année théorique.
     * Par construction non LCD (durée > 30 j, pas un mois civil entier).
     */
    private function fullYearSyntheticContract(int $year): Contract
    {
        $contract = new Contract([
            'vehicle_id' => 0,
            'company_id' => 0,
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
            'contract_reference' => null,
            'contract_type' => ContractType::Lld,
            'notes' => null,
        ]);

        // Force les casts (Eloquent ne caste pas les attributs hors
        // sauvegarde DB).
        $contract->setRawAttributes([
            'start_date' => sprintf('%04d-01-01', $year),
            'end_date' => sprintf('%04d-12-31', $year),
            'contract_type' => ContractType::Lld->value,
        ], true);

        return $contract;
    }
}
