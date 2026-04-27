<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Assignment;

use App\Data\User\Assignment\VehicleDatesData;
use App\DTO\Fiscal\AnnualCumulByPair;
use App\Models\Assignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Assignment.
 *
 * Centralise tous les accès Eloquent autour des attributions, y compris
 * les agrégations (`GROUP BY`) consommées par le moteur fiscal.
 */
interface AssignmentReadRepositoryInterface
{
    /**
     * Cumul annuel des jours par couple (vehicle, company), agrégé en
     * une seule requête SQL `GROUP BY`.
     */
    public function loadAnnualCumul(int $year): AnnualCumulByPair;

    /**
     * Densité hebdomadaire (vehicleId × week_number → nb jours) pour
     * la heatmap planning.
     *
     * @return array<string, int> Clés "vehicleId|weekNumber" → jours
     */
    public function loadWeekDensity(int $year): array;

    /**
     * Dates occupées d'un véhicule sur l'année + map companyId →
     * dates pour ce véhicule.
     */
    public function findVehicleDates(int $vehicleId, int $year): VehicleDatesData;

    /**
     * Attributions d'un véhicule sur la fenêtre [start, end], avec
     * eager-loading de la company (cols minimales).
     *
     * @return Collection<int, Assignment>
     */
    public function findWeekAssignments(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

    /**
     * Liste des dates ISO (Y-m-d) déjà attribuées au couple (vehicle,
     * company) sur l'année donnée. Utilisé par le preview taxes.
     *
     * @return list<string>
     */
    public function findDatesForPair(int $vehicleId, int $companyId, int $year): array;

    /**
     * Compte les attributions de l'année (hors soft-deleted).
     */
    public function countForYear(int $year): int;
}
