<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Driver;

use App\Models\Contract;
use App\Models\Driver;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures Driver — interface slim conforme ADR-0013 (zéro transformation,
 * zéro décision métier ; les retours sont des Models / Collections bruts).
 *
 * Toute composition de DTO ou agrégat vit dans
 * {@see DriverQueryService}.
 */
interface DriverReadRepositoryInterface
{
    public function findById(int $id): ?Driver;

    /**
     * Driver avec memberships company + counts contrats — pour la page Show.
     */
    public function findByIdWithRelations(int $id): ?Driver;

    /**
     * Liste paginée pour la page Index drivers (toutes companies confondues).
     *
     * @return Collection<int, Driver>
     */
    public function listAllForIndex(): Collection;

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
     * la company donnée — pour exposer la liste dans la modale Q6.
     *
     * @return Collection<int, Contract>
     */
    public function listFutureContractsInCompany(
        int $driverId,
        int $companyId,
        CarbonInterface $leftAt,
    ): Collection;
}
