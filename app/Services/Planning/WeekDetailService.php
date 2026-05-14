<?php

declare(strict_types=1);

namespace App\Services\Planning;

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
 * `[min(dates), max(dates)]` - sémantique cohérente avec la sélection
 * par plage début/fin du DateRangePicker.
 */
final class WeekDetailService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
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

        // Toutes les dates de l'année où le véhicule est déjà sous
        // contrat - pour empêcher la sélection conflictuelle dans le
        // DateRangePicker même quand l'utilisateur navigue sur d'autres
        // semaines / mois que la semaine ouverte au drawer.
        $vehicleBusyDates = $this->contractQuery->findDatesForVehicleInRange(
            $vehicleId,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );

        return new PlanningWeekData(
            weekNumber: $weekNumber,
            weekStart: $start->toDateString(),
            weekEnd: $end->toDateString(),
            vehicleId: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            days: $days,
            companiesOnWeek: $companiesOnWeek,
            vehicleBusyDates: $vehicleBusyDates,
        );
    }

    /**
     * Variante company-scoped de {@see buildWeek} pour la Vue Entreprise
     * (chantier P3). Anonymise les contrats des autres entreprises dans
     * la grille semaine et filtre `companiesOnWeek` pour ne garder que
     * l'entreprise demandée.
     *
     * Sécurité : le frontend ne reçoit jamais l'identité ni la couleur
     * de l'entreprise occupante quand le contrat n'est pas le sien.
     */
    public function buildWeekForCompany(
        int $vehicleId,
        int $weekNumber,
        int $year,
        int $companyId,
    ): PlanningWeekData {
        $week = $this->buildWeek($vehicleId, $weekNumber, $year);

        $anonymizedDays = array_map(
            static function (WeekDaySlotData $slot) use ($companyId): WeekDaySlotData {
                if ($slot->contract === null) {
                    return $slot;
                }

                if ($slot->contract->company->id === $companyId) {
                    return $slot;
                }

                return new WeekDaySlotData(
                    date: $slot->date,
                    dayLabel: $slot->dayLabel,
                    contract: null,
                    hasUnavailability: $slot->hasUnavailability,
                    isOccupiedByOther: true,
                );
            },
            $week->days,
        );

        $filteredCompaniesOnWeek = array_values(array_filter(
            $week->companiesOnWeek,
            static fn (WeekCompanyPresenceData $entry): bool => $entry->company->id === $companyId,
        ));

        return new PlanningWeekData(
            weekNumber: $week->weekNumber,
            weekStart: $week->weekStart,
            weekEnd: $week->weekEnd,
            vehicleId: $week->vehicleId,
            licensePlate: $week->licensePlate,
            days: $anonymizedDays,
            companiesOnWeek: $filteredCompaniesOnWeek,
            vehicleBusyDates: $week->vehicleBusyDates,
        );
    }

    /**
     * Aperçu fiscal **standalone** d'une attribution (location/contrat).
     *
     * Sémantique : LCD/LLD se qualifie **contrat par contrat
     * individuellement** d'après la durée du contrat seul (≤ 30 j → LCD,
     * sinon LLD). Aucune notion de cumul annuel pour un couple véhicule
     * × entreprise. Le preview calcule donc strictement le coût fiscal
     * de **ce contrat précis** : ses jours, sa CO₂, ses polluants, son
     * total, ses exonérations applicables.
     *
     * On simule un contrat synthétique unique sur `[min(dates),
     * max(dates)]` sans tenir compte d'autres contrats existants pour
     * le même couple. Si la plage est partiellement chevauchante avec
     * un contrat existant, l'aperçu reste indicatif (la création réelle
     * passera par `BulkCreateContractsAction` qui détectera l'overlap).
     */
    public function previewTaxes(PreviewTaxesInputData $input, int $year): FiscalPreviewData
    {
        $yearPrefix = $year.'-';

        $newDates = array_values(array_filter(
            $input->dates,
            static fn (string $d): bool => str_starts_with($d, $yearPrefix),
        ));

        $vehicle = $this->vehicles->findOrFailWithFiscal($input->vehicleId);
        $unavailabilities = $this->unavailabilityRepo->findForVehicle($input->vehicleId)->all();

        if ($newDates === []) {
            $emptyBreakdown = $this->calculator->calculate($vehicle, [], $unavailabilities, $year);

            return new FiscalPreviewData(
                fiscalYear: $year,
                daysCount: 0,
                breakdown: FiscalBreakdownData::fromBreakdown($emptyBreakdown),
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

        $daysCount = $syntheticContract->countDaysInYear($year);

        $breakdown = $this->calculator->calculate(
            $vehicle,
            [$syntheticContract],
            $unavailabilities,
            $year,
        );

        return new FiscalPreviewData(
            fiscalYear: $year,
            daysCount: $daysCount,
            breakdown: FiscalBreakdownData::fromBreakdown($breakdown),
        );
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
