<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\User\Contract\ContractData;
use App\Data\User\Contract\ContractListItemData;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\DataCollection;

/**
 * Service Query du domaine Contract — composition pure des
 * Collections retournées par le Repository en DataCollection<DTO>,
 * et helpers de calcul utilisés par le moteur fiscal (chantier 04.F).
 *
 * Conforme ADR-0013 : zéro SQL ici, uniquement de la transformation
 * et du calcul applicatif.
 */
final readonly class ContractQueryService
{
    public function __construct(
        private ContractReadRepositoryInterface $repository,
    ) {}

    public function findContractData(int $id): ?ContractData
    {
        $contract = $this->repository->findByIdWithRelations($id);

        if ($contract === null) {
            return null;
        }

        return ContractData::fromModel($contract);
    }

    /**
     * Liste paginée pour la page Index (chantier 04.G).
     *
     * @return DataCollection<int, ContractListItemData>
     */
    public function listAll(): DataCollection
    {
        $contracts = $this->repository->listAll();

        /** @var DataCollection<int, ContractListItemData> */
        return ContractListItemData::collect(
            $contracts->map(static fn (Contract $c): ContractListItemData => ContractListItemData::fromModel($c)),
            DataCollection::class,
        );
    }

    /**
     * Liste des contrats d'une entreprise utilisatrice (page company show).
     *
     * @return DataCollection<int, ContractListItemData>
     */
    public function listForCompany(int $companyId): DataCollection
    {
        $contracts = $this->repository->listForCompany($companyId);

        /** @var DataCollection<int, ContractListItemData> */
        return ContractListItemData::collect(
            $contracts->map(static fn (Contract $c): ContractListItemData => ContractListItemData::fromModel($c)),
            DataCollection::class,
        );
    }

    /**
     * Expansion d'un contrat en jours ISO (Y-m-d), bornée à l'année
     * passée en argument. Utilisée par le moteur fiscal en 04.F pour
     * calculer le numérateur du prorata (R-2024-002).
     *
     * @return list<string>
     */
    public function expandToDays(Contract $contract, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $rangeStart = $contract->start_date->isAfter($yearStart) ? $contract->start_date : $yearStart;
        $rangeEnd = $contract->end_date->isBefore($yearEnd) ? $contract->end_date : $yearEnd;

        if ($rangeStart->isAfter($rangeEnd)) {
            return [];
        }

        $days = [];
        $cursor = CarbonImmutable::parse($rangeStart->toDateString());
        $stop = CarbonImmutable::parse($rangeEnd->toDateString());

        while (! $cursor->isAfter($stop)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
