<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Unavailability;

use App\Models\Unavailability;
use App\Services\Unavailability\UnavailabilityQueryService;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Unavailability.
 *
 * Aucune transformation ni composition de DTO ici (R3) - retourne
 * des Collections de Models bruts ou des arrays primitifs. La
 * composition vit dans {@see UnavailabilityQueryService}.
 */
interface UnavailabilityReadRepositoryInterface
{
    /**
     * Toutes les indispos d'un véhicule (hors soft-deleted), triées
     * par `start_date DESC` pour affichage antéchronologique.
     *
     * @return Collection<int, Unavailability>
     */
    public function findForVehicle(int $vehicleId): Collection;

    /**
     * Indispos de plusieurs véhicules en **un seul SELECT IN** -
     * retourne un map `vehicleId → list<Unavailability>` (véhicule
     * sans indispo absent du map ; aux appelants de défaulter sur `[]`).
     *
     * Remplace l'antipattern N+1 d'une boucle PHP appelant
     * {@see self::findForVehicle()} pour chaque id.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<Unavailability>>
     */
    public function findForVehicleIds(array $vehicleIds): array;

    /**
     * Lookup unitaire - échoue si l'id n'existe pas (404).
     */
    public function findById(int $id): Unavailability;

    /**
     * Nombre de jours d'indisponibilité (tous types confondus) par
     * semaine ISO de l'année. Utilisé pour dimensionner un segment
     * « indispo » empilé au-dessus des attributions dans la timeline
     * 52 semaines de la fiche véhicule.
     *
     * Une indispo couvrant 3 jours d'une semaine retournera `[N => 3]`
     * (et non pas la semaine entière comme un overlay).
     *
     * @return array<int, int> weekNumber (1-53) → jours d'indispo (1-7)
     */
    public function findUnavailableDaysByWeekForVehicle(int $vehicleId, int $year): array;

    /**
     * Indispos d'un véhicule dont la plage `[start_date, end_date]`
     * déborde la date passée - c'est-à-dire `end_date > $date` ou
     * `end_date IS NULL` (indispo encore ouverte).
     *
     * Utilisé par {@see App\Services\Vehicle\VehicleExitImpactComputer}
     * pour énumérer les conflits qui bloqueraient une sortie de flotte
     * proposée à `$date` (cf. ADR-0018 § 8.1).
     *
     * @return Collection<int, Unavailability>
     */
    public function findActiveOverlappingDateForVehicle(int $vehicleId, string $date): Collection;
}
