<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Fiscal\FiscalBreakdownData;
use App\Data\User\Fiscal\FiscalPreviewData;
use App\Data\User\Planning\PlanningWeekData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekCompanyPresenceData;
use App\Data\User\Planning\WeekDayContractData;
use App\Data\User\Planning\WeekDaySlotData;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Support\Carbon;

/**
 * Détail d'une semaine pour le drawer planning + preview des taxes
 * induites par la création d'un nouveau contrat.
 *
 * La preview simule l'ajout d'un contrat synthétique sur la plage
 * `[min(dates), max(dates)]` — sémantique cohérente avec la sélection
 * par plage début/fin du DateRangePicker.
 */
final class WeekDetailService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractReadRepositoryInterface $contractRepo,
        private readonly ContractQueryService $contractQuery,
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
        private readonly FiscalCalculator $calculator,
    ) {}

    /**
     * Construit le payload du drawer pour une semaine donnée d'un véhicule.
     *
     * Liste les jours de la semaine ; pour chaque jour, on rapporte
     * l'éventuel contrat actif qui le couvre (1 contrat max par jour
     * grâce au trigger anti-overlap).
     */
    public function buildWeek(int $vehicleId, int $weekNumber, int $year): PlanningWeekData
    {
        $vehicle = $this->vehicles->findOrFailWithFiscal($vehicleId);

        $start = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $weekContracts = $this->contractQuery->findWindowContractsForVehicle(
            $vehicleId,
            $start,
            $end,
        );

        // ADR-0019 D5 : pour la bordure rouge des jours d'indispo dans
        // la grille « État de la semaine », on charge UNE fois les
        // indispos du véhicule croisant la fenêtre semaine et on filtre
        // par jour côté PHP.
        $weekUnavailabilities = $this->unavailabilityRepo
            ->findForVehicle($vehicleId)
            ->filter(static fn ($u): bool => $u->start_date->lessThanOrEqualTo($end)
                && ($u->end_date === null || $u->end_date->greaterThanOrEqualTo($start)));

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $iso = $cursor->toDateString();
            $contract = $weekContracts->first(
                static fn (Contract $c): bool => $iso >= $c->start_date->toDateString()
                    && $iso <= $c->end_date->toDateString(),
            );

            $hasUnavailabilityOnDay = $weekUnavailabilities->contains(
                static fn ($u): bool => $u->start_date->toDateString() <= $iso
                    && ($u->end_date === null || $u->end_date->toDateString() >= $iso),
            );

            $days[] = new WeekDaySlotData(
                date: $iso,
                dayLabel: $cursor->translatedFormat('D d'),
                contract: $contract !== null
                    ? new WeekDayContractData(
                        id: $contract->id,
                        company: new CompanyOptionData(
                            id: $contract->company->id,
                            shortCode: $contract->company->short_code,
                            legalName: $contract->company->legal_name,
                            color: $contract->company->color,
                        ),
                    )
                    : null,
                hasUnavailability: $hasUnavailabilityOnDay,
            );
            $cursor->addDay();
        }

        $companiesOnWeek = $this->buildCompaniesOnWeek($weekContracts, $start, $end);

        return new PlanningWeekData(
            weekNumber: $weekNumber,
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            vehicleId: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            days: $days,
            companiesOnWeek: $companiesOnWeek,
        );
    }

    /**
     * Aperçu fiscal des taxes induites par l'ajout d'une plage de
     * dates pour un couple (véhicule, entreprise).
     *
     * Sémantique 04.F : on simule l'ajout d'un **contrat synthétique
     * unique** sur la plage `[min(dates), max(dates)]`. Si la plage est
     * en partie chevauchante avec un contrat existant du couple,
     * l'aperçu reste indicatif (la création réelle passera par
     * `BulkCreateContractsAction` qui détectera l'overlap).
     */
    public function previewTaxes(PreviewTaxesInputData $input, int $year): FiscalPreviewData
    {
        $yearPrefix = $year.'-';

        $newDates = array_values(array_filter(
            $input->dates,
            static fn (string $d): bool => str_starts_with($d, $yearPrefix),
        ));

        // Préparation : un seul aller-retour DB pour les 3 entités
        // requises par le calculator quel que soit le chemin (avec ou
        // sans nouvelles dates) — auparavant ces lectures partaient
        // dans chaque branche du if.
        $vehicle = $this->vehicles->findOrFailWithFiscal($input->vehicleId);
        $unavailabilities = $this->unavailabilityRepo->findForVehicle($input->vehicleId)->all();
        $existingContracts = $this->contractRepo
            ->findByVehicleAndYear($input->vehicleId, $year)
            ->all();
        $existingForPair = array_values(array_filter(
            $existingContracts,
            static fn (Contract $c): bool => $c->company_id === $input->companyId,
        ));

        $existingDates = $this->collectDates($existingForPair, $year);
        $existingCumul = count($existingDates);

        $before = $existingCumul > 0
            ? $this->calculator->calculate($vehicle, $existingForPair, $unavailabilities, $year)
            : null;

        if ($newDates === []) {
            $after = $this->calculator->calculate($vehicle, $existingForPair, $unavailabilities, $year);

            return new FiscalPreviewData(
                fiscalYear: $year,
                newDaysCount: 0,
                existingCumul: $existingCumul,
                futureCumul: $existingCumul,
                before: $before !== null ? FiscalBreakdownData::fromBreakdown($before) : null,
                after: FiscalBreakdownData::fromBreakdown($after),
                incrementalDue: 0.0,
            );
        }

        sort($newDates);
        $rangeStart = $newDates[0];
        $rangeEnd = $newDates[count($newDates) - 1];

        $syntheticContract = $this->buildSyntheticContract(
            $input->vehicleId,
            $input->companyId,
            $rangeStart,
            $rangeEnd,
        );

        $newDatesSet = [];
        foreach ($syntheticContract->expandToDaysInYear($year) as $date) {
            if (! in_array($date, $existingDates, true)) {
                $newDatesSet[] = $date;
            }
        }
        $newDaysCount = count($newDatesSet);
        $futureCumul = $existingCumul + $newDaysCount;

        $after = $this->calculator->calculate(
            $vehicle,
            [...$existingForPair, $syntheticContract],
            $unavailabilities,
            $year,
        );

        $incrementalDue = $after->totalDue - ($before?->totalDue ?? 0.0);

        return new FiscalPreviewData(
            fiscalYear: $year,
            newDaysCount: $newDaysCount,
            existingCumul: $existingCumul,
            futureCumul: $futureCumul,
            before: $before !== null ? FiscalBreakdownData::fromBreakdown($before) : null,
            after: FiscalBreakdownData::fromBreakdown($after),
            incrementalDue: round($incrementalDue, 2),
        );
    }

    /**
     * @param  iterable<Contract>  $contracts
     * @return list<string>
     */
    private function collectDates(iterable $contracts, int $year): array
    {
        $dates = [];
        foreach ($contracts as $contract) {
            foreach ($contract->expandToDaysInYear($year) as $date) {
                $dates[$date] = true;
            }
        }

        return array_keys($dates);
    }

    /**
     * Contrat synthétique non-persisté pour la simulation fiscale.
     */
    private function buildSyntheticContract(
        int $vehicleId,
        int $companyId,
        string $startDate,
        string $endDate,
    ): Contract {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicleId,
            'company_id' => $companyId,
            'driver_id' => null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'contract_reference' => null,
            'contract_type' => ContractType::Lcd->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    /**
     * Compose la liste des entreprises présentes sur la semaine avec
     * le nombre de jours occupés par chacune.
     *
     * @param  iterable<Contract>  $weekContracts
     * @return list<WeekCompanyPresenceData>
     */
    private function buildCompaniesOnWeek(iterable $weekContracts, Carbon $start, Carbon $end): array
    {
        $byCompany = [];
        foreach ($weekContracts as $contract) {
            $companyId = $contract->company_id;
            $byCompany[$companyId] ??= [
                'company' => $contract->company,
                'days' => [],
            ];

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $iso = $cursor->toDateString();
                if ($iso >= $contract->start_date->toDateString()
                    && $iso <= $contract->end_date->toDateString()
                ) {
                    $byCompany[$companyId]['days'][$iso] = true;
                }
                $cursor->addDay();
            }
        }

        $rows = [];
        foreach ($byCompany as $entry) {
            $company = $entry['company'];
            $rows[] = new WeekCompanyPresenceData(
                company: new CompanyOptionData(
                    id: $company->id,
                    shortCode: $company->short_code,
                    legalName: $company->legal_name,
                    color: $company->color,
                ),
                days: count($entry['days']),
            );
        }

        return $rows;
    }
}
