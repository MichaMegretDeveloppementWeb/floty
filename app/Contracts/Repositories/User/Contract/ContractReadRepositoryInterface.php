<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Contract;

use App\Data\User\Contract\ContractIndexQueryData;
use App\Models\Contract;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * Liste paginée server-side de l'Index Contracts (cf. ADR-0020).
     * Applique `{search, vehicleId, companyId, driverId, type,
     * periodStart, periodEnd, sortKey, sortDirection, page, perPage}`
     * du DTO en SQL pure.
     *
     * Search : LIKE sur `vehicle.license_plate, vehicle.brand,
     * vehicle.model, company.short_code, company.legal_name,
     * driver.first_name, driver.last_name` via `whereHas`.
     *
     * Sort whitelist : vehicle | company | startDate | endDate |
     * duration | type. `vehicle`/`company` utilisent un join temporaire
     * pour ordonner sur la colonne textuelle de la relation. `duration`
     * via `DATEDIFF(end_date, start_date)`.
     *
     * Filtre période : chevauchement
     * (`start_date <= periodEnd AND end_date >= periodStart`).
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginateForIndex(ContractIndexQueryData $query): LengthAwarePaginator;

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

    /**
     * Compte total des contrats (non soft-deleted) d'une entreprise,
     * tous exercices confondus. Alimente la stat lifetime
     * `contractsCount` de la fiche entreprise (chantier K, ADR-0020 D3).
     */
    public function countForCompany(int $companyId): int;

    /**
     * Liste triée et dédoublonnée des années (ISO calendaire) au cours
     * desquelles l'entreprise a au moins un contrat actif (peu importe
     * que le contrat couvre l'année entière ou n'y déborde que
     * partiellement).
     *
     * Une entreprise dont le seul contrat va du 15/12/2024 au 10/01/2025
     * remonte donc `[2024, 2025]`.
     *
     * Alimente :
     *   - `availableYears` (peuple le sélecteur d'année local de la fiche)
     *   - les itérations du `history` (1 entrée `CompanyYearStatsData`
     *     par année)
     *
     * @return list<int>
     */
    public function findActiveYearsForCompany(int $companyId): array;
}
