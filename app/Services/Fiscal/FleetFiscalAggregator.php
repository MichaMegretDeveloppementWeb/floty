<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\ContractTaxYearBreakdownData;
use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\PipelineResult;
use App\Models\Contract;
use App\Models\FiscalRule;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Collection;

/**
 * Agrégateur fiscal annuel à l'échelle de la flotte.
 *
 * Centralise les sommations de taxe (par véhicule, par entreprise, par
 * flotte) qui étaient dupliquées dans 4 controllers (Vehicle, Company,
 * Dashboard, Planning).
 *
 * **Note R-2024-003 (sémantique BOFiP)** : l'arrondi half-up à l'euro
 * est appliqué **une seule fois par redevable** (entreprise utilisatrice),
 * jamais par couple intermédiaire. L'aggregator somme les `*DueRaw` des
 * `PipelineResult` et arrondit en sortie. Cf. ADR-0006 § 2.
 *
 * **Refonte 04.F (ADR-0014)** : passe de `AnnualCumulByPair` à
 * `ContractsByPair`. Les indispos par véhicule sont passées séparément
 * (map `vehicleId → list<Unavailability>`) pour alimenter R-2024-008.
 */
final class FleetFiscalAggregator
{
    /**
     * Cache mémoire intra-instance des Collections de règles fiscales
     * indexé par `"{year}|{sortedCodes}"`. Évite les lectures DB
     * répétées quand l'aggregator est réutilisé sur plusieurs
     * véhicules / contrats à l'intérieur d'une même requête (ex.
     * VehicleQueryService::buildUsageStats appelle
     * vehicleFullYearTaxBreakdown plusieurs fois indirectement).
     *
     * @var array<string, Collection<int, FiscalRule>>
     */
    private array $rulesCache = [];

    /**
     * Cache mémoire intra-instance des `PipelineResult` du « coût plein
     * année théorique » indexé par `"{vehicleId}|{year}"`. Le résultat
     * dépend exclusivement du véhicule et de l'année (contrat full-year
     * synthétique, indispos vides), il est donc partageable entre
     * `vehicleFullYearTax` et `vehicleFullYearTaxBreakdown` - la liste
     * Flotte gagne ~50 % de pipeline runs.
     *
     * @var array<string, PipelineResult>
     */
    private array $fullYearResultCache = [];

    public function __construct(
        private readonly FiscalPipeline $pipeline,
        private readonly FiscalYearContext $yearContext,
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
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
     * **Coût plein année théorique** d'un véhicule : ce qu'il
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
     * Détail complet du calcul du coût plein année d'un véhicule -
     * affiché dans la sidebar de la page Show pour expliquer comment
     * le total a été obtenu (méthode CO₂, catégorie polluants,
     * exonérations appliquées, codes règles).
     */
    public function vehicleFullYearTaxBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $result = $this->fullYearPipelineResult($vehicle, $year);

        $co2Tariff = round($result->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
        $pollutantsTariff = round($result->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);

        $vfc = $vehicle->fiscalCharacteristics->firstWhere(
            static fn ($v): bool => $v->effective_to === null,
        );

        $appliedRules = $this->loadRulesByCodes($year, $result->appliedRuleCodes)
            ->map(static fn (FiscalRule $r): FiscalRuleListItemData => FiscalRuleListItemData::fromModel($r))
            ->values()
            ->all();

        return new VehicleFullYearTaxBreakdownData(
            co2Method: $result->co2Method,
            co2FullYearTariff: $co2Tariff,
            co2Explanation: $this->buildCo2Explanation($vfc, $result->co2Method, $co2Tariff, $year),
            pollutantCategory: $result->pollutantCategory,
            pollutantsFullYearTariff: $pollutantsTariff,
            pollutantsExplanation: $this->buildPollutantsExplanation($vfc, $result->pollutantCategory, $pollutantsTariff),
            appliedExemptions: array_map(
                static fn ($e) => AppliedExemptionData::fromValueObject($e),
                $result->appliedExemptions,
            ),
            appliedRuleCodes: $result->appliedRuleCodes,
            total: round($result->co2DueRaw + $result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP),
            appliedRules: $appliedRules,
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
            $result = $this->pipeline->execute(
                $this->buildContext($vehicle, [$contract], $vehicleUnavailabilities, $year),
            );

            $daysInContractInYear = count($contract->expandToDaysInYear($year));

            $co2Tariff = round($result->co2FullYearTariff, 2, PHP_ROUND_HALF_UP);
            $pollutantsTariff = round($result->pollutantsFullYearTariff, 2, PHP_ROUND_HALF_UP);
            $co2Due = round($result->co2DueRaw, 2, PHP_ROUND_HALF_UP);
            $pollutantsDue = round($result->pollutantsDueRaw, 2, PHP_ROUND_HALF_UP);
            $yearTotalDue = round($co2Due + $pollutantsDue, 2, PHP_ROUND_HALF_UP);

            $appliedRules = $this->loadRulesByCodes($year, $result->appliedRuleCodes)
                ->map(static fn (FiscalRule $r): FiscalRuleListItemData => FiscalRuleListItemData::fromModel($r))
                ->values()
                ->all();

            $years[] = new ContractTaxYearBreakdownData(
                year: $year,
                daysInContractInYear: $daysInContractInYear,
                daysAssigned: $result->daysAssigned,
                daysInYear: $result->daysInYear,
                co2Method: $result->co2Method,
                pollutantCategory: $result->pollutantCategory,
                co2FullYearTariff: $co2Tariff,
                pollutantsFullYearTariff: $pollutantsTariff,
                co2Due: $co2Due,
                pollutantsDue: $pollutantsDue,
                totalDue: $yearTotalDue,
                appliedExemptions: array_map(
                    static fn ($e) => AppliedExemptionData::fromValueObject($e),
                    $result->appliedExemptions,
                ),
                appliedRuleCodes: $result->appliedRuleCodes,
                appliedRules: $appliedRules,
            );

            $totalRaw += $result->co2DueRaw + $result->pollutantsDueRaw;
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
     * fiche Company Show.
     *
     * @param  Collection<int, Vehicle>  $vehiclesById  Indexée par id
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId
     * @return list<array{vehicleId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    public function companyAnnualTaxBreakdownByVehicle(
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
     * @param  list<string>  $codes
     * @return Collection<int, FiscalRule>
     */
    private function loadRulesByCodes(int $year, array $codes): Collection
    {
        sort($codes);
        $key = $year.'|'.implode(',', $codes);

        return $this->rulesCache[$key] ??= $this->fiscalRules->findByCodesForYear($year, $codes);
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
     * → 31 déc), utilisé pour calculer le coût plein année théorique.
     * Par construction non LCD (durée > 30 j, pas un mois civil entier).
     */
    private function fullYearSyntheticContract(int $year): Contract
    {
        $contract = new Contract([
            'vehicle_id' => 0,
            'company_id' => 0,
            'driver_id' => null,
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
