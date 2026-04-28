<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Assignment;

use App\Models\Assignment;
use App\Services\Assignment\AssignmentQueryService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Assignment.
 *
 * Conforme à la règle stricte des couches (mémoire architecture
 * `architecture_layered_strict.md`) : aucune transformation, aucune
 * composition de DTO complexe, aucune décision métier ici. Le repo
 * retourne des Collections / Models bruts, et la composition vit dans
 * {@see AssignmentQueryService}.
 *
 * Le filtre métier R-2024-008 (déduction des jours fourrière du
 * numérateur du prorata) reste exprimé dans la requête SQL agrégée
 * de {@see loadAnnualCumulRows()} — c'est de la **donnée filtrée**,
 * pas de la transformation post-fetch.
 */
interface AssignmentReadRepositoryInterface
{
    /**
     * Cumul annuel agrégé en SQL `GROUP BY` (vehicle_id, company_id).
     * Filtre les jours d'indisponibilité fiscale (fourrière, R-2024-008).
     *
     * @return Collection<int, object{vehicle_id: int, company_id: int, days: int}>
     */
    public function loadAnnualCumulRows(int $year): Collection;

    /**
     * Toutes les attributions de l'année (cols minimales) pour le
     * calcul de densité hebdomadaire.
     *
     * @return Collection<int, Assignment>
     */
    public function findAssignmentsForYear(int $year): Collection;

    /**
     * Toutes les attributions d'un véhicule sur l'année (cols
     * minimales : company_id, date).
     *
     * @return Collection<int, Assignment>
     */
    public function findAssignmentsForVehicle(int $vehicleId, int $year): Collection;

    /**
     * Attributions d'un véhicule sur la fenêtre [start, end], avec
     * eager-loading de la company (cols minimales) — la composition
     * du payload est faite par le service consommateur.
     *
     * @return Collection<int, Assignment>
     */
    public function findWeekAssignments(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

    /**
     * Dates brutes des attributions du couple (vehicle, company)
     * sur l'année. La conversion en strings ISO est faite par le
     * service consommateur.
     *
     * @return Collection<int, CarbonInterface>
     */
    public function findDatesForPair(int $vehicleId, int $companyId, int $year): Collection;

    /**
     * Compte les attributions de l'année (hors soft-deleted).
     */
    public function countForYear(int $year): int;
}
