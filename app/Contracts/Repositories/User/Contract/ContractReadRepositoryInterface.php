<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures Contract — interface slim conforme ADR-0013 (zéro
 * transformation, zéro décision métier ; les retours sont des
 * Collections / Models bruts. Toute composition de DTO ou agrégat
 * vit dans {@see ContractQueryService}).
 */
interface ContractReadRepositoryInterface
{
    public function findById(int $id): ?Contract;

    public function findByIdWithRelations(int $id): ?Contract;

    /**
     * Liste des contrats actifs d'un véhicule sur l'année — utilisée
     * par le moteur fiscal pour expansion en jours (cf. R-2024-002).
     *
     * @return Collection<int, Contract>
     */
    public function findByVehicleAndYear(int $vehicleId, int $year): Collection;

    /**
     * Liste des contrats actifs d'une entreprise utilisatrice.
     *
     * @return Collection<int, Contract>
     */
    public function listForCompany(int $companyId): Collection;

    /**
     * Liste des contrats actifs d'un véhicule (toutes périodes).
     *
     * @return Collection<int, Contract>
     */
    public function listForVehicle(int $vehicleId): Collection;

    /**
     * Recherche un contrat actif sur le même véhicule dont la plage
     * `[start_date, end_date]` chevauche celle passée en argument.
     * Utilisé par les Actions Store/Update pour la validation
     * applicative (défense en profondeur avant le trigger DB).
     *
     * Le `excludeId` permet d'exclure le contrat en cours d'édition.
     */
    public function findOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
        ?int $excludeId = null,
    ): ?Contract;

    /**
     * Liste paginée pour la page Index (chantier 04.G).
     *
     * @return Collection<int, Contract>
     */
    public function listAll(): Collection;
}
