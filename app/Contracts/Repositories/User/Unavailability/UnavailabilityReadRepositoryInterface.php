<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Unavailability;

use App\Models\Unavailability;
use App\Services\Unavailability\UnavailabilityQueryService;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Unavailability.
 *
 * Aucune transformation ni composition de DTO ici (R3) — retourne
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
     * Lookup unitaire — échoue si l'id n'existe pas (404).
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
}
