<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Driver;

use App\Data\User\Driver\DriverIndexQueryData;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Pivot\DriverCompany;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures Driver - interface slim conforme ADR-0013 (zéro transformation,
 * zéro décision métier ; les retours sont des Models / Collections bruts).
 *
 * Toute composition de DTO ou agrégat vit dans
 * {@see DriverQueryService}.
 */
interface DriverReadRepositoryInterface
{
    public function findById(int $id): ?Driver;

    /**
     * Driver avec memberships company + counts contrats - pour la page Show.
     */
    public function findByIdWithRelations(int $id): ?Driver;

    /**
     * Liste paginée pour la page Index drivers (toutes companies confondues).
     *
     * @return Collection<int, Driver>
     *
     * @deprecated Conservé temporairement pour compatibilité — sera retiré
     *             en L6 du chantier ADR-0020 (cleanup pagination V1.1) une
     *             fois les 4 pilotes Index migrés et stabilisés. Utiliser
     *             {@see paginateForIndex()}.
     */
    public function listAllForIndex(): Collection;

    /**
     * Liste paginée server-side de l'Index drivers (cf. ADR-0020).
     * Applique les paramètres `{search, sortKey, sortDirection, page,
     * perPage}` du DTO en SQL pur via `where`/`orderBy`/`paginate`.
     *
     * Eager-load des memberships actives + counts contracts + count
     * companies actives pour permettre le tri sur `contractsCount` et
     * `activeCompaniesCount`.
     *
     * @return LengthAwarePaginator<int, Driver>
     */
    public function paginateForIndex(DriverIndexQueryData $query): LengthAwarePaginator;

    /**
     * Liste plate de tous les drivers (triés nom/prénom). Utilisé par le
     * filtre conducteur du Contracts Index, qui n'est pas restreint à une
     * company / période.
     *
     * @return Collection<int, Driver>
     */
    public function listAllForOptions(): Collection;

    /**
     * Liste des drivers ayant **au moins une membership** (active ou
     * historique) avec la company donnée. Utilisé par la section
     * Conducteurs de Show Company.
     *
     * @return Collection<int, Driver>
     */
    public function listForCompany(int $companyId, bool $includeInactive = true): Collection;

    /**
     * Drivers actifs dans la company sur **toute la période** [start, end] :
     * `joined_at <= start AND (left_at IS NULL OR left_at >= end)`.
     * Utilisé par le picker driver dans le formulaire Contract.
     *
     * @return Collection<int, Driver>
     */
    public function listActiveInCompanyDuring(
        int $companyId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection;

    /**
     * Compte les contrats à venir (`start_date > leftAt`) du driver dans
     * la company donnée. Utilisé par le workflow Q6 de pose de `left_at`.
     */
    public function countFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): int;

    /**
     * Liste les contrats à venir (`start_date > leftAt`) du driver dans
     * la company donnée - pour exposer la liste dans la modale Q6.
     *
     * @return Collection<int, Contract>
     */
    public function listFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): Collection;

    /**
     * Compte les contrats du driver groupés par company (1 seule requête).
     * Utilisé par le détail driver pour alimenter les memberships sans N+1.
     *
     * @return array<int, int> Map `[companyId => count]`
     */
    public function countContractsForDriverGroupedByCompany(int $driverId): array;

    /**
     * Récupère la membership active la plus récente du driver dans la
     * company donnée. Utilisée par le workflow Q6 (sortie d'une entreprise).
     */
    public function findActiveMembership(int $driverId, int $companyId): ?DriverCompany;

    /**
     * Récupère une membership par son id de pivot. Utilisée par le détachement.
     */
    public function findMembershipById(int $pivotId): ?DriverCompany;

    /**
     * Compte le nombre total de contrats référençant ce driver (toutes
     * companies, toutes périodes). Utilisé par la vérification pré-suppression.
     */
    public function countContractsForDriver(int $driverId): int;
}
