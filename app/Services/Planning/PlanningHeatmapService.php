<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Planning\PlanningHeatmapCompanyVehicleData;
use App\Data\User\Planning\PlanningHeatmapVehicleData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Unavailability;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\DataCollection;

/**
 * Construction de la matrice véhicules × 52 semaines pour la page
 * « Vue d'ensemble » (planning).
 *
 * **Refonte 04.F (ADR-0014)** : la heatmap consomme désormais les
 * contrats (`ContractQueryService`). Les indispos par véhicule sont
 * passées au moteur fiscal pour permettre à R-2024-008 d'agir sur la
 * matière brute.
 */
final class PlanningHeatmapService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
    ) {}

    /**
     * @return array{vehicles: DataCollection<int, PlanningHeatmapVehicleData>, companies: DataCollection<int, CompanyOptionData>}
     */
    public function buildHeatmap(int $year): array
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $weekDensity = $this->contracts->loadWeekDensity($year);

        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= 52; $w++) {
                $weeks[] = $weekDensity[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];

            // Tolère une année hors règles fiscales codées (cf. doctrine
            // « données métier ⊥ règles fiscales »). On affiche 0/0 plutôt
            // que de crasher la heatmap.
            try {
                $fullYear = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
                $fullYearTax = $fullYear->total;
                $dailyTaxRate = $fullYear->daysInYear > 0
                    ? round($fullYear->total / $fullYear->daysInYear, 2, PHP_ROUND_HALF_UP)
                    : 0.0;
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
                $dailyTaxRate = 0.0;
            }

            $vehicleRows[] = new PlanningHeatmapVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeks: $weeks,
                daysTotal: array_sum($weeks),
                annualTaxDue: $this->aggregator->vehicleAnnualTax(
                    $vehicle,
                    $contractsByPair,
                    $vehicleUnavailabilities,
                    $year,
                ),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                fullYearTax: $fullYearTax,
                dailyTaxRate: $dailyTaxRate,
            );
        }

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapVehicleData::collect($vehicleRows, DataCollection::class),
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Variante company-scoped de {@see buildHeatmap} pour la Vue
     * Entreprise (chantier P1). Le **chiffre cellule** ne reflète que
     * les jours utilisés par l'entreprise sélectionnée ; la **couleur**
     * reste pilotée par la densité globale (taux d'occupation toutes
     * entreprises confondues = signal de disponibilité du véhicule).
     *
     * Le total annuel ligne (`daysTotalForCompany` et
     * `annualTaxDueForCompany`) agrège uniquement la part de
     * l'entreprise pour ce véhicule.
     *
     * @return array{
     *     vehicles: DataCollection<int, PlanningHeatmapCompanyVehicleData>,
     *     company: CompanyOptionData,
     *     companies: DataCollection<int, CompanyOptionData>,
     * }
     */
    public function buildHeatmapForCompany(int $year, Company $company): array
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $weekDensityGlobal = $this->contracts->loadWeekDensity($year);
        $weekDensityForCompany = $this->contracts->loadWeekDensityForCompany($year, $company->id);

        // ContractsByPair filtré pour ne garder que les paires de
        // l'entreprise demandée. `vehicleAnnualTax` itère ensuite sur
        // `pairsForVehicle` et ne trouvera donc qu'une seule paire (ou
        // zéro), évitant de comptabiliser les autres entreprises.
        $contractsForCompany = new ContractsByPair(
            array_filter(
                $contractsByPair->byPair,
                static fn (string $key): bool => str_ends_with($key, '|'.$company->id),
                ARRAY_FILTER_USE_KEY,
            ),
        );

        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeksGlobal = [];
            $weeksForCompany = [];
            for ($w = 1; $w <= 52; $w++) {
                $weeksGlobal[] = $weekDensityGlobal[$vehicle->id.'|'.$w] ?? 0;
                $weeksForCompany[] = $weekDensityForCompany[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];

            try {
                $fullYear = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
                $fullYearTax = $fullYear->total;
                $dailyTaxRate = $fullYear->daysInYear > 0
                    ? round($fullYear->total / $fullYear->daysInYear, 2, PHP_ROUND_HALF_UP)
                    : 0.0;
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
                $dailyTaxRate = 0.0;
            }

            $vehicleRows[] = new PlanningHeatmapCompanyVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeksGlobal: $weeksGlobal,
                weeksForCompany: $weeksForCompany,
                daysTotalForCompany: array_sum($weeksForCompany),
                annualTaxDueForCompany: $this->aggregator->vehicleAnnualTax(
                    $vehicle,
                    $contractsForCompany,
                    $vehicleUnavailabilities,
                    $year,
                ),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                fullYearTax: $fullYearTax,
                dailyTaxRate: $dailyTaxRate,
            );
        }

        $companyData = new CompanyOptionData(
            id: $company->id,
            shortCode: $company->short_code,
            legalName: $company->legal_name,
            color: $company->color,
        );

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapCompanyVehicleData::collect($vehicleRows, DataCollection::class),
            'company' => $companyData,
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Liste triée et dédoublonnée des numéros de semaines ISO (1-52) où
     * au moins un jour d'indisponibilité (tous types confondus) tombe
     * dans l'année fiscale demandée.
     *
     * Alimente la bordure rouge sur les cellules heatmap (ADR-0019 D5)
     * - visibilité immédiate de la cohabitation indispo↔contrat.
     *
     * @param  list<Unavailability>  $unavailabilities
     * @return list<int>
     */
    private function collectWeeksWithUnavailability(array $unavailabilities, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();

        $weeks = [];
        foreach ($unavailabilities as $unavailability) {
            // Filtre indispos hors année (équivalent du WHERE SQL).
            if ($unavailability->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($unavailability->end_date !== null && $unavailability->end_date->lessThan($yearStart)) {
                continue;
            }

            $start = $unavailability->start_date->greaterThan($yearStart)
                ? $unavailability->start_date
                : $yearStart;
            $end = $unavailability->end_date === null || $unavailability->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $unavailability->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $weeks[(int) $cursor->isoWeek] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $list = array_keys($weeks);
        sort($list);

        return $list;
    }
}
