<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Data\User\Assignment\VehicleDatesData;
use App\DTO\Fiscal\AnnualCumulByPair;
use Illuminate\Support\Carbon;

/**
 * Composition / transformation des données du domaine Assignment.
 *
 * Le repository ne fait que retourner des Collections brutes ; ce
 * service assemble les DTOs métier à partir de ces données. Conforme
 * à la règle stricte des couches (mémoire `architecture_layered_strict`).
 *
 * Les méthodes du repo qui retournent déjà du raw exploitable
 * directement (`findWeekAssignments`, `countForYear`) ne sont pas
 * ré-exposées ici : par R2, un passe-plat de service est inutile, les
 * consommateurs peuvent appeler le repo en direct.
 */
final readonly class AssignmentQueryService
{
    public function __construct(
        private AssignmentReadRepositoryInterface $repository,
    ) {}

    /**
     * Cumul annuel des jours d'attribution par couple, prêt à être
     * consommé par le moteur fiscal.
     */
    public function loadAnnualCumul(int $year): AnnualCumulByPair
    {
        $byPair = [];
        foreach ($this->repository->loadAnnualCumulRows($year) as $row) {
            $byPair[$row->vehicle_id.'|'.$row->company_id] = (int) $row->days;
        }

        return new AnnualCumulByPair($byPair);
    }

    /**
     * Densité hebdomadaire (vehicleId × week_number → nb jours)
     * pour la heatmap planning.
     *
     * @return array<string, int> Clés "vehicleId|weekNumber" → jours
     */
    public function loadWeekDensity(int $year): array
    {
        $density = [];
        foreach ($this->repository->findAssignmentsForYear($year) as $assignment) {
            $week = (int) Carbon::parse($assignment->date)->isoWeek;
            $key = $assignment->vehicle_id.'|'.$week;
            $density[$key] = ($density[$key] ?? 0) + 1;
        }

        return $density;
    }

    /**
     * Dates occupées d'un véhicule + map companyId → dates pour ce
     * véhicule, pour alimenter le calendrier multi-dates côté front.
     */
    public function findVehicleDates(int $vehicleId, int $year): VehicleDatesData
    {
        $busy = [];
        $byCompany = [];

        foreach ($this->repository->findAssignmentsForVehicle($vehicleId, $year) as $assignment) {
            $iso = $assignment->date->toDateString();
            $busy[] = $iso;
            $byCompany[(string) $assignment->company_id] ??= [];
            $byCompany[(string) $assignment->company_id][] = $iso;
        }

        return new VehicleDatesData(
            vehicleBusyDates: array_values(array_unique($busy)),
            pairDates: $byCompany,
        );
    }

    /**
     * Dates ISO (Y-m-d) déjà attribuées au couple (vehicle, company)
     * sur l'année. Utilisé par le preview taxes.
     *
     * @return list<string>
     */
    public function findDatesForPair(int $vehicleId, int $companyId, int $year): array
    {
        return $this->repository
            ->findDatesForPair($vehicleId, $companyId, $year)
            ->map(static fn ($date): string => Carbon::parse($date)->toDateString())
            ->values()
            ->all();
    }
}
