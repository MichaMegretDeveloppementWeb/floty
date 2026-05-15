<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyActivityYearData;
use App\Data\User\Company\CompanyDetailData;
use App\Data\User\Company\CompanyDriverRowData;
use App\Data\User\Company\CompanyLifetimeStatsData;
use App\Data\User\Company\CompanyTopVehicleData;
use App\Data\User\Company\CompanyYearStatsData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Pivot\DriverCompany;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Support\Carbon;

/**
 * Détail complet d'une entreprise pour la page Show (extrait de
 * `CompanyQueryService` pour respecter SRP · Lot 4 D08 / F-11-004).
 *
 * Concentre le flot le plus complexe du domaine Company (~180 l de
 * logique principale) qui agrège tout pour la page Show ·
 *   - hero d'identité (intemporel)
 *   - KPIs lifetime cumulés
 *   - section « Historique par année »
 *   - sous-stats par année (heatmap mensuelle + top 3 véhicules)
 *   - liste des drivers avec memberships actives + sorties
 *
 * **Doctrine temporelle (chantier η Phase 1)** · 3 lentilles distinctes ·
 *   - Présent · KPIs en haut (année calendaire courante)
 *   - Lifetime · KPIs cumulés toutes années confondues
 *   - Historique · 1 ligne par année du scope global, MÊME les années à 0
 */
final class CompanyDetailService
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly ContractReadRepositoryInterface $contractsRepo,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
        private readonly RentalPriceCalculator $rentalPrice,
    ) {}

    /**
     * Détail complet d'une entreprise pour la page Show · alimente :
     *  - le hero d'identité (intemporel)
     *  - les KPIs lifetime cumulés (`lifetime`)
     *  - la section « Historique par année » (`history`)
     *  - les onglets restants (Drivers, etc.) qui consomment les
     *    champs identitaires existants
     *
     * `currentRealYear` est exposé pour permettre à l'historique de
     * marquer l'exercice en cours sans dépendre de `new Date()`
     * côté front (cf. ADR-0020 D4).
     */
    public function detail(int $companyId): ?CompanyDetailData
    {
        $company = $this->companies->findById($companyId);
        if ($company === null) {
            return null;
        }

        $today = Carbon::today();
        $currentRealYear = (int) $today->year;

        // Drivers de cette entreprise (toutes memberships, actives + sorties)
        $company->load(['drivers' => function ($query): void {
            $query->orderByPivot('joined_at');
        }]);

        // Compte de contrats par driver dans la company donnée. Pivot
        // N:N `contract_drivers` (cf. chantier #3 multi-conducteurs) :
        // un contrat avec 2 drivers compte 1 fois pour chacun.
        //
        // Lot 4 D04 (F-34-006) · agrégation déléguée à
        // `ContractReadRepository::countContractsByDriverForCompany`
        // (conformité ADR-0013 R3 · pas de SQL direct dans les Services).
        $contractsCountByDriver = $this->contractsRepo->countContractsByDriverForCompany($companyId);

        $driverRows = $company->drivers->map(function ($driver) use ($contractsCountByDriver): CompanyDriverRowData {
            /** @var DriverCompany $pivot */
            $pivot = $driver->getAttribute('pivot');
            $first = (string) ($driver->first_name ?? '');
            $last = (string) ($driver->last_name ?? '');
            $fullName = trim($first.' '.$last);
            $initials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));

            return new CompanyDriverRowData(
                driverId: $driver->id,
                pivotId: $pivot->id,
                fullName: $fullName !== '' ? $fullName : '-',
                initials: $initials !== '' ? $initials : '-',
                joinedAt: $pivot->joined_at->toDateString(),
                leftAt: $pivot->left_at?->toDateString(),
                // Sémantique alignée sur `findActiveMembership` write-side
                // (`left_at IS NULL`). Cf. chantier B + cohérence avec
                // `DriverQueryService::detail`.
                isCurrentlyActive: $pivot->left_at === null,
                contractsCount: (int) ($contractsCountByDriver[$driver->id] ?? 0),
            );
        })->values()->all();

        $activeDriversCount = 0;
        foreach ($driverRows as $row) {
            if ($row->isCurrentlyActive) {
                $activeDriversCount++;
            }
        }

        // ADR-0020 D3 · calcul des stats temporelles (lifetime + history)
        $contractsCount = $this->contracts->countContractsForCompany($companyId);
        $availableYears = $this->contracts->findActiveYearsForCompany($companyId);

        // F-11-001 · 1 chargement bulk en range au lieu de 2×N appels
        // year-by-year à `loadContractsByPair()` depuis `computeYearStats`
        // et `computeActivityForYear`. Le pivot pré-résolu est passé en
        // paramètre aux 2 sous-méthodes pour court-circuiter leur
        // chargement interne.
        $contractsByYear = [];
        if ($availableYears !== []) {
            $contractsByYear = $this->contracts->loadContractsByPairForYearRange(
                min($availableYears),
                max($availableYears),
            );
        }

        // Toutes les années où l'entreprise a au moins un contrat,
        // utilisées pour pré-calculer history + activityByYear (sans
        // distinction encore présent/passé).
        $allYearStats = [];
        foreach ($availableYears as $year) {
            $allYearStats[$year] = $this->computeYearStats(
                $companyId,
                $year,
                $contractsByYear[$year] ?? null,
            );
        }

        $lifetime = new CompanyLifetimeStatsData(
            daysUsed: array_sum(array_map(static fn (CompanyYearStatsData $s): int => $s->daysUsed, $allYearStats)),
            contractsCount: $contractsCount,
            taxesGenerated: round(
                array_sum(array_map(static fn (CompanyYearStatsData $s): float => $s->annualTaxDue, $allYearStats)),
                2,
                PHP_ROUND_HALF_UP,
            ),
            // V1.2 · la facturation des loyers n'est pas livrée. Le champ
            // est exposé en placeholder null pour que l'UI le rende dès
            // maintenant (carte KPI, branchement réel quand le module
            // facturation arrive).
            rentTotal: null,
        );

        // **Doctrine temporelle (chantier η Phase 1)** : 3 lentilles distinctes.
        //
        // Présent · KPIs en haut de page, toujours sur l'année calendaire
        // courante. Si l'entreprise n'a pas de contrat sur cette année,
        // on retourne un CompanyYearStatsData neutre (zéros) · l'UI
        // affichera "0 j / 0 contrats / 0 €" sans crash.
        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $allYearStats[$kpiYear] ?? $this->emptyYearStats($kpiYear);

        // Distingue "données absentes" (KPIs à 0) de "calcul fiscal
        // impossible" (règles fiscales pas encore codées pour kpiYear).
        // Permet à l'UI d'afficher un message court explicite sur la
        // KPI Taxes uniquement (cf. doctrine HD6).
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // Évolution · section Historique : toutes les années passées du
        // scope global `[minYear..kpiYear-1]`, MÊME celles où cette
        // entreprise n'a aucun contrat (lignes neutres à zéros). Une
        // année à 0 sur la fiche Entreprise est une info utile (« cette
        // année-là, l'entreprise n'a rien utilisé »). Cohérent avec la
        // doctrine HD4 : bornes globales partagées par toutes les pages.
        // Si la DB est vide globalement, `minYear == kpiYear` → boucle
        // vide → état empty UI déclenché.
        $historyMinYear = $this->availableYears->minYear();
        $history = [];
        for ($year = $historyMinYear; $year < $kpiYear; $year++) {
            $history[] = $allYearStats[$year] ?? $this->emptyYearStats($year);
        }

        return new CompanyDetailData(
            id: $company->id,
            legalName: $company->legal_name,
            shortCode: $company->short_code,
            color: $company->color,
            siren: $company->siren,
            siret: $company->siret,
            addressLine1: $company->address_line_1,
            addressLine2: $company->address_line_2,
            postalCode: $company->postal_code,
            city: $company->city,
            country: $company->country,
            contactName: $company->contact_name,
            contactEmail: $company->contact_email,
            contactPhone: $company->contact_phone,
            isActive: $company->is_active,
            isOig: $company->is_oig,
            isIndividualBusiness: $company->is_individual_business,
            contractsCount: $contractsCount,
            activeDriversCount: $activeDriversCount,
            totalDriversCount: count($driverRows),
            drivers: $driverRows,
            lifetime: $lifetime,
            kpiStats: $kpiStats,
            kpiYear: $kpiYear,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            activityByYear: array_map(
                fn (int $year): CompanyActivityYearData => $this->computeActivityForYear(
                    $companyId,
                    $year,
                    $contractsByYear[$year] ?? null,
                ),
                $availableYears,
            ),
            availableYears: $availableYears,
            currentRealYear: $currentRealYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Calcule l'activité détaillée d'une entreprise pour un exercice :
     * heatmap mensuelle (12 entiers, jours-véhicules / mois) + top 3
     * véhicules (triés desc par jours utilisés).
     *
     * Cette méthode dépend des informations véhicule (licensePlate,
     * brand, model) · les charge via le repo en bulk pour éviter les
     * N+1 lors de l'itération. Si l'entreprise n'a aucun pair sur
     * l'année (cas `availableYears` partiellement vide), retourne un
     * `CompanyActivityYearData` à zéros (12 cases vides + top vide).
     *
     * F-11-001 · `$preloadedPair` permet à l'appelant (cf. `detail()`)
     * de fournir un pivot déjà résolu en bulk via
     * `loadContractsByPairForYearRange()` · économise N appels à
     * `loadContractsByPair($year)`.
     */
    private function computeActivityForYear(
        int $companyId,
        int $year,
        ?ContractsByPair $preloadedPair = null,
    ): CompanyActivityYearData {
        $contractsByPair = $preloadedPair ?? $this->contracts->loadContractsByPair($year);

        // Pré-passe : on accumule par véhicule (pour le top) et par mois
        // (pour la heatmap), à partir des couples de l'entreprise sur
        // l'année. Un jour-véhicule = 1 unité ; deux véhicules attribués
        // simultanément le même jour = 2 unités sur le compteur du mois.
        /** @var array<int, int> $daysPerVehicle */
        $daysPerVehicle = [];
        $daysByMonth = array_fill(0, 12, 0);

        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            foreach ($pairContracts as $contract) {
                foreach ($contract->expandToDaysInYear($year) as $iso) {
                    $monthIndex = (int) substr($iso, 5, 2) - 1; // YYYY-MM-DD → 0..11
                    $daysByMonth[$monthIndex]++;
                    $daysPerVehicle[$vehicleId] = ($daysPerVehicle[$vehicleId] ?? 0) + 1;
                }
            }
        }

        if ($daysPerVehicle === []) {
            return new CompanyActivityYearData(
                year: $year,
                daysByMonth: $daysByMonth,
                topVehicles: [],
            );
        }

        // Top 3 véhicules · tri desc, limite 3.
        arsort($daysPerVehicle);
        $topVehicleIds = array_slice(array_keys($daysPerVehicle), 0, 3, preserve_keys: true);

        // Lookup bulk pour récupérer license_plate + brand + model des
        // véhicules du top (au plus 3 · coût négligeable).
        $vehiclesById = $this->vehicles->findByIdsIndexed($topVehicleIds);

        $totalVehicleDays = (int) array_sum($daysPerVehicle);

        $topVehicles = [];
        foreach ($topVehicleIds as $vehicleId) {
            $vehicle = $vehiclesById->get($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $days = $daysPerVehicle[$vehicleId];
            $topVehicles[] = new CompanyTopVehicleData(
                vehicleId: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                daysUsed: $days,
                percentage: $totalVehicleDays > 0
                    ? round($days / $totalVehicleDays * 100, 1)
                    : 0.0,
            );
        }

        return new CompanyActivityYearData(
            year: $year,
            daysByMonth: $daysByMonth,
            topVehicles: $topVehicles,
        );
    }

    /**
     * Stats neutres (tous compteurs à zéro) pour une année où l'entreprise
     * n'a aucun contrat. Utilisé pour les KPIs et pour combler les trous
     * dans l'historique.
     */
    private function emptyYearStats(int $year): CompanyYearStatsData
    {
        return new CompanyYearStatsData(
            year: $year,
            daysUsed: 0,
            contractsCount: 0,
            lcdCount: 0,
            lldCount: 0,
            annualTaxDue: 0.0,
            rent: null,
        );
    }

    /**
     * Calcule les KPIs annuels d'une entreprise pour une année donnée.
     * Charge les contrats de l'année (toutes flottes via aggregator) puis
     * filtre sur le couple `(vehicleId, $companyId)` côté `ContractsByPair`.
     *
     * F-11-001 · `$preloadedPair` permet à l'appelant (cf. `detail()`)
     * de fournir un pivot déjà résolu en bulk via
     * `loadContractsByPairForYearRange()` · économise N appels à
     * `loadContractsByPair($year)`.
     */
    private function computeYearStats(
        int $companyId,
        int $year,
        ?ContractsByPair $preloadedPair = null,
    ): CompanyYearStatsData {
        $contractsByPair = $preloadedPair ?? $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        $lcdCount = 0;
        $lldCount = 0;
        foreach ($contractsByPair->pairsForCompany($companyId) as $vehicleId => $pairContracts) {
            $vehicleIds[] = $vehicleId;
            foreach ($pairContracts as $contract) {
                if ($contract->contract_type === ContractType::Lcd) {
                    $lcdCount++;
                } else {
                    $lldCount++;
                }
            }
        }

        $daysUsed = $contractsByPair->daysByCompany($companyId, $year);

        $annualTaxDue = 0.0;
        if ($vehicleIds !== []) {
            try {
                $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIds);
                $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
                $annualTaxDue = $this->aggregator->companyAnnualTax(
                    $companyId,
                    $vehiclesById,
                    $contractsByPair,
                    $unavailabilitiesByVehicleId,
                    $year,
                );
            } catch (FiscalCalculationException) {
                // L'année n'est pas configurée dans le calculateur
                // (cf. `config/floty.fiscal.available_years`). On laisse
                // `annualTaxDue: 0.0` plutôt que faire crasher la page ·
                // l'utilisateur voit quand même les jours et le compte
                // de contrats pour cet exercice. Cas typique : contrats
                // antérieurs à la config fiscale, ou en avance sur
                // celle-ci.
                $annualTaxDue = 0.0;
            }
        }

        $rentCents = $this->rentalPrice->forCompanyAndYear($companyId, $year);
        $rent = $rentCents === null ? null : $rentCents / 100;

        return new CompanyYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $lcdCount + $lldCount,
            lcdCount: $lcdCount,
            lldCount: $lldCount,
            annualTaxDue: $annualTaxDue,
            rent: $rent,
        );
    }
}
