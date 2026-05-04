<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures Contract - interface slim conforme ADR-0013 (zéro
 * transformation, zéro décision métier ; les retours sont des
 * Collections / Models bruts. Toute composition de DTO ou agrégat
 * vit dans {@see ContractQueryService}).
 */
interface ContractReadRepositoryInterface
{
    public function findById(int $id): ?Contract;

    public function findByIdWithRelations(int $id): ?Contract;

    /**
     * Liste des contrats actifs d'un véhicule sur l'année - utilisée
     * par le moteur fiscal pour expansion en jours (cf. R-2024-002).
     *
     * @return Collection<int, Contract>
     */
    public function findByVehicleAndYear(int $vehicleId, int $year): Collection;

    /**
     * Tous les contrats actifs croisant l'année - pivot du moteur fiscal
     * (composé en `ContractsByPair` dans le service). Eager-load
     * minimal pour les agrégations.
     *
     * @return Collection<int, Contract>
     */
    public function findActiveForYear(int $year): Collection;

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
     * Liste des contrats actifs d'un véhicule chevauchant la fenêtre
     * `[start, end]`. Eager-load `company` pour le drawer semaine.
     *
     * @return Collection<int, Contract>
     */
    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

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
     * Tous les contrats actifs d'un véhicule chevauchant `[start, end]`.
     * Différent de {@see findOverlapping} qui retourne le premier
     * conflit pour vérification booléenne ; utilisé par les services
     * qui doivent ENUMÉRER les conflits (typiquement pour exposer la
     * liste exhaustive de dates conflictuelles à un utilisateur).
     *
     * @return Collection<int, Contract>
     */
    public function findAllOverlapping(
        int $vehicleId,
        string $startDate,
        string $endDate,
    ): Collection;

    /**
     * Liste paginée pour la page Index (chantier 04.G).
     *
     * @return Collection<int, Contract>
     */
    public function listAll(): Collection;

    /**
     * Tous les contrats actifs (toutes plates) chevauchant la fenêtre
     * `[start, end]`. Utilisé pour pré-calculer la table
     * `vehicleId → busyDates` consommée par le picker du formulaire
     * Contract Create/Edit (chantier H).
     *
     * @return Collection<int, Contract>
     */
    public function findAllInWindow(string $start, string $end): Collection;

    /**
     * Compte les contrats référençant ce driver dans cette company
     * (toutes périodes confondues). Utilisé par
     * `DetachDriverCompanyMembershipAction` pour bloquer la suppression
     * d'une membership encore liée à des contrats.
     */
    public function countForDriverInCompany(int $driverId, int $companyId): int;
}
