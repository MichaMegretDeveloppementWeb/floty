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
     * Numéros de semaines ISO de l'année qui contiennent au moins
     * 1 jour d'indisponibilité (tous types confondus). Utilisé pour
     * marquer la timeline 52 semaines de la fiche véhicule.
     *
     * @return list<int> Semaines ISO (1-53), ordre croissant
     */
    public function findOverlappingWeeksForVehicle(int $vehicleId, int $year): array;
}
