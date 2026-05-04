<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Company\CompanyContractsStatsData;
use App\Data\User\Contract\ContractData;
use App\Data\User\Contract\ContractDocumentData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Data\User\Contract\ContractListItemData;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\PaginatedContractListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Service Query du domaine Contract - composition pure des
 * Collections retournées par le Repository en DataCollection<DTO>,
 * et helpers d'agrégation utilisés par le moteur fiscal et les
 * services consommateurs (cf. chantier 04.F).
 *
 * Conforme ADR-0013 : zéro SQL ici, uniquement de la transformation
 * et du calcul applicatif.
 */
final readonly class ContractQueryService
{
    public function __construct(
        private ContractReadRepositoryInterface $repository,
        private UnavailabilityReadRepositoryInterface $unavailabilityRepository,
        private ContractDocumentReadRepositoryInterface $documentRepository,
        private FleetFiscalAggregator $aggregator,
    ) {}

    /**
     * Liste les documents PDF joints à un contrat (chantier 04.N).
     *
     * @return list<ContractDocumentData>
     */
    public function listDocumentsForContract(int $contractId): array
    {
        return $this->documentRepository
            ->listForContract($contractId)
            ->map(static fn ($d): ContractDocumentData => ContractDocumentData::fromModel($d))
            ->values()
            ->all();
    }

    public function findContractData(int $id): ?ContractData
    {
        $contract = $this->repository->findByIdWithRelations($id);

        if ($contract === null) {
            return null;
        }

        return ContractData::fromModel($contract);
    }

    /**
     * Façade fiscale pour la page détail contrat. Charge le contrat
     * (avec vehicle.fiscalCharacteristics eager-loadées via
     * `findByIdWithRelations`) et les indispos du véhicule, puis
     * délègue à {@see FleetFiscalAggregator::contractTaxBreakdown}.
     *
     * Retourne `null` si le contrat est introuvable (cohérent avec
     * `findContractData`).
     */
    public function findContractTaxBreakdown(int $id): ?ContractTaxBreakdownData
    {
        $contract = $this->repository->findByIdWithRelations($id);

        if ($contract === null) {
            return null;
        }

        $unavailabilities = $this->unavailabilityRepository
            ->findForVehicle($contract->vehicle_id)
            ->all();

        return $this->aggregator->contractTaxBreakdown($contract, $unavailabilities);
    }

    /**
     * Index Contracts paginé server-side (cf. ADR-0020). Le repo gère
     * pagination + filtres + search + tri en SQL pure ; le service mappe
     * les models en DTO.
     */
    public function listPaginated(ContractIndexQueryData $query): PaginatedContractListData
    {
        $paginator = $this->repository->paginateForIndex($query);

        $items = array_map(
            static fn (Contract $c): ContractListItemData => ContractListItemData::fromModel($c),
            $paginator->items(),
        );

        return new PaginatedContractListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Stats contextuelles affichées sous le titre de l'onglet Contrats
     * de la fiche Company Show (chantier N.1.fixes). Les jours sont en
     * intersection avec la fenêtre filtrée (cf. doc repo).
     */
    public function statsForCompany(
        int $companyId,
        ?string $periodStart,
        ?string $periodEnd,
    ): CompanyContractsStatsData {
        $row = $this->repository->statsForCompanyInPeriod(
            $companyId,
            $periodStart,
            $periodEnd,
        );

        return new CompanyContractsStatsData(
            totalDays: $row['totalDays'],
            lcdCount: $row['lcdCount'],
            lldCount: $row['lldCount'],
        );
    }

    /**
     * Plage continue `[firstYear..currentRealYear]` pour les pills de
     * filtre rapide année (chantier N.1.fixes). Si l'entreprise n'a
     * aucun contrat, retourne un tableau vide — les pills ne sont
     * pas affichées (l'empty state suffit).
     *
     * Différent de `availableYears` (= années avec ≥ 1 contrat) :
     * la plage des pills est continue pour ne pas créer de "trous"
     * visuels et pour permettre de filtrer une année creuse afin de
     * confirmer l'absence de contrats.
     *
     * @return list<int>
     */
    public function availableYearsRangeForCompany(
        int $companyId,
        int $currentRealYear,
    ): array {
        $first = $this->repository->firstContractYearForCompany($companyId);
        if ($first === null) {
            return [];
        }

        return range($first, $currentRealYear);
    }

    /**
     * Variante de `listPaginated` qui force le `companyId` à la valeur
     * passée en paramètre — utilisée par l'onglet Contrats de la fiche
     * Company (chantier N.1). On ne fait pas confiance au query param
     * de l'URL : la fiche Company impose son propre `companyId`.
     */
    public function listPaginatedForCompany(
        int $companyId,
        ContractIndexQueryData $query,
    ): PaginatedContractListData {
        $scoped = new ContractIndexQueryData(
            vehicleId: $query->vehicleId,
            companyId: $companyId,
            driverId: $query->driverId,
            type: $query->type,
            periodStart: $query->periodStart,
            periodEnd: $query->periodEnd,
            page: $query->page,
            perPage: $query->perPage,
            search: $query->search,
            sortKey: $query->sortKey,
            sortDirection: $query->sortDirection,
        );

        return $this->listPaginated($scoped);
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
     * Pivot du moteur fiscal - tous les contrats actifs croisant
     * l'année regroupés par couple (vehicleId, companyId).
     */
    public function loadContractsByPair(int $year): ContractsByPair
    {
        $byPair = [];
        foreach ($this->repository->findActiveForYear($year) as $contract) {
            $key = $contract->vehicle_id.'|'.$contract->company_id;
            $byPair[$key] ??= [];
            $byPair[$key][] = $contract;
        }

        return new ContractsByPair($byPair);
    }

    /**
     * Variante scopée d'un seul véhicule - utilisée par la page Show
     * vehicle qui n'a aucun usage des autres véhicules de la flotte.
     *
     * Évite de matérialiser le pivot complet (cf. `loadContractsByPair`)
     * juste pour en filtrer 1/N à l'arrivée.
     */
    public function loadContractsByPairForVehicle(int $vehicleId, int $year): ContractsByPair
    {
        $byPair = [];
        foreach ($this->repository->findByVehicleAndYear($vehicleId, $year) as $contract) {
            $key = $contract->vehicle_id.'|'.$contract->company_id;
            $byPair[$key] ??= [];
            $byPair[$key][] = $contract;
        }

        return new ContractsByPair($byPair);
    }

    /**
     * Indispos par véhicule pour alimenter R-2024-008 (la règle filtre
     * elle-même les indispos qui croisent l'année et les contrats
     * taxables - voir `R2024_008_ReductiveUnavailability::evaluate()`).
     *
     * Délègue à un `WHERE vehicle_id IN (?)` unique côté repository ;
     * un véhicule sans indispo est absent du map (les appelants
     * défaultent sur `[]` à la lecture).
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<Unavailability>>
     */
    public function loadUnavailabilitiesByVehicle(array $vehicleIds): array
    {
        return $this->unavailabilityRepository->findForVehicleIds($vehicleIds);
    }

    /**
     * Compte total des contrats d'une entreprise (toutes années).
     * Délégué au repo, exposé via le service pour respecter la chaîne
     * d'appels Service → Service → Repository (cf. ADR-0013) consommée
     * par {@see CompanyQueryService::detail()}.
     */
    public function countContractsForCompany(int $companyId): int
    {
        return $this->repository->countForCompany($companyId);
    }

    /**
     * Liste triée des années où l'entreprise a au moins un contrat
     * actif (cf. {@see ContractReadRepositoryInterface::findActiveYearsForCompany}).
     *
     * @return list<int>
     */
    public function findActiveYearsForCompany(int $companyId): array
    {
        return $this->repository->findActiveYearsForCompany($companyId);
    }

    /**
     * Pour le formulaire Contract Create/Edit : table
     * `vehicleId → list<date ISO>` des jours déjà occupés par un autre
     * contrat actif du véhicule, sur une fenêtre [today − 2 ans, today
     * + 2 ans] qui couvre largement les saisies réalistes.
     *
     * Le picker de plage côté front consomme cette table pour griser les
     * jours non-sélectionnables et empêcher l'utilisateur de tomber
     * dans le filet du trigger MySQL anti-overlap.
     *
     * Pour l'écran Edit, on exclut les dates du contrat en cours
     * d'édition via `excludeContractId` - sinon l'utilisateur ne pourrait
     * pas réenregistrer son contrat sans le « déplacer » d'abord.
     *
     * @return array<int, list<string>>
     */
    public function busyDatesByVehicleAroundToday(?int $excludeContractId = null): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subYears(2)->startOfYear()->toDateString();
        $to = $today->addYears(2)->endOfYear()->toDateString();

        $contracts = $this->repository->findAllInWindow($from, $to);

        $byVehicle = [];
        foreach ($contracts as $contract) {
            if ($excludeContractId !== null && $contract->id === $excludeContractId) {
                continue;
            }
            $vehicleId = $contract->vehicle_id;
            if (! isset($byVehicle[$vehicleId])) {
                $byVehicle[$vehicleId] = [];
            }
            foreach ($this->expandContractToRange($contract, $from, $to) as $date) {
                $byVehicle[$vehicleId][$date] = true;
            }
        }

        $result = [];
        foreach ($byVehicle as $vehicleId => $datesMap) {
            $list = array_keys($datesMap);
            sort($list);
            $result[$vehicleId] = $list;
        }

        return $result;
    }

    /**
     * Liste des dates ISO occupées par un véhicule sur une période -
     * source de `busyDates` côté page Vehicle Show (calendrier indispo).
     *
     * @return list<string>
     */
    public function findDatesForVehicleInRange(int $vehicleId, string $from, string $to): array
    {
        $contracts = $this->repository
            ->findWindowContractsForVehicle(
                $vehicleId,
                CarbonImmutable::parse($from),
                CarbonImmutable::parse($to),
            );

        $dates = [];
        foreach ($contracts as $contract) {
            foreach ($this->expandContractToRange($contract, $from, $to) as $date) {
                $dates[$date] = true;
            }
        }
        $list = array_keys($dates);
        sort($list);

        return $list;
    }

    /**
     * Dates ISO d'un couple sur l'année - preview taxes (incrément
     * journalier potentiel d'un nouvel ajout dans le drawer planning).
     *
     * @return list<string>
     */
    public function findDatesForPair(int $vehicleId, int $companyId, int $year): array
    {
        $dates = [];
        foreach ($this->repository->findByVehicleAndYear($vehicleId, $year) as $contract) {
            if ($contract->company_id !== $companyId) {
                continue;
            }
            foreach ($contract->expandToDaysInYear($year) as $date) {
                $dates[$date] = true;
            }
        }
        $list = array_keys($dates);
        sort($list);

        return $list;
    }

    /**
     * Breakdown hebdomadaire `week → companyId → days` pour la
     * timeline 52 semaines de la page Vehicle Show.
     *
     * @return array<int, array<int, int>>
     */
    public function loadVehicleWeeklyBreakdown(int $vehicleId, int $year): array
    {
        $contracts = $this->repository->findByVehicleAndYear($vehicleId, $year);

        /** @var array<int, array<int, array<string, bool>>> $byWeekCompanyDays */
        $byWeekCompanyDays = [];
        foreach ($contracts as $contract) {
            foreach ($contract->expandToDaysInYear($year) as $date) {
                $week = (int) (new \DateTimeImmutable($date))->format('W');
                $byWeekCompanyDays[$week] ??= [];
                $byWeekCompanyDays[$week][$contract->company_id] ??= [];
                $byWeekCompanyDays[$week][$contract->company_id][$date] = true;
            }
        }

        $byWeek = [];
        foreach ($byWeekCompanyDays as $week => $byCompany) {
            $byWeek[$week] = [];
            foreach ($byCompany as $companyId => $days) {
                $byWeek[$week][$companyId] = count($days);
            }
        }
        ksort($byWeek);

        return $byWeek;
    }

    /**
     * Densité par véhicule × semaine ISO de l'année - heatmap planning.
     * Clé = `"vehicleId|weekNumber"` ; valeur = nombre de jours
     * occupés par au moins un contrat du véhicule cette semaine.
     *
     * @return array<string, int>
     */
    public function loadWeekDensity(int $year): array
    {
        $contracts = $this->repository->findActiveForYear($year);

        /** @var array<string, array<string, bool>> $byKeyDays */
        $byKeyDays = [];
        foreach ($contracts as $contract) {
            foreach ($contract->expandToDaysInYear($year) as $date) {
                $week = (int) (new \DateTimeImmutable($date))->format('W');
                $key = $contract->vehicle_id.'|'.$week;
                $byKeyDays[$key] ??= [];
                $byKeyDays[$key][$date] = true;
            }
        }

        $density = [];
        foreach ($byKeyDays as $key => $days) {
            $density[$key] = count($days);
        }

        return $density;
    }

    /**
     * Contrats du véhicule chevauchant la fenêtre [start, end] -
     * drawer semaine planning (avec relation `company` eager-loaded).
     *
     * @return Collection<int, Contract>
     */
    public function findWindowContractsForVehicle(
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): Collection {
        return $this->repository->findWindowContractsForVehicle($vehicleId, $start, $end);
    }

    /**
     * Expansion d'un contrat en jours ISO (Y-m-d), bornée à l'année
     * passée en argument. Délègue à {@see Contract::expandToDaysInYear()}
     * (helper réutilisé par les règles fiscales).
     *
     * Méthode conservée pour compat avec les consommateurs qui
     * passaient par le service.
     *
     * @return list<string>
     */
    public function expandToDays(Contract $contract, int $year): array
    {
        return $contract->expandToDaysInYear($year);
    }

    /**
     * Expansion d'un contrat en jours ISO clampée à une fenêtre
     * `[from, to]` arbitraire (pas forcément une année).
     *
     * @return list<string>
     */
    private function expandContractToRange(Contract $contract, string $from, string $to): array
    {
        $rangeStart = CarbonImmutable::parse($from);
        $rangeEnd = CarbonImmutable::parse($to);

        $start = CarbonImmutable::parse($contract->start_date->toDateString());
        $end = CarbonImmutable::parse($contract->end_date->toDateString());

        $cursorStart = $start->isAfter($rangeStart) ? $start : $rangeStart;
        $cursorEnd = $end->isBefore($rangeEnd) ? $end : $rangeEnd;

        if ($cursorStart->isAfter($cursorEnd)) {
            return [];
        }

        $days = [];
        $cursor = $cursorStart;
        while (! $cursor->isAfter($cursorEnd)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
