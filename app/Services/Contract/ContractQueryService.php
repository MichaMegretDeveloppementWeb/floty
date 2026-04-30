<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\User\Contract\ContractData;
use App\Data\User\Contract\ContractDocumentData;
use App\Data\User\Contract\ContractListItemData;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\DTO\Fiscal\ContractsByPair;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Services\Fiscal\FleetFiscalAggregator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Service Query du domaine Contract — composition pure des
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
     * Pivot du moteur fiscal — tous les contrats actifs croisant
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
     * Indispos par véhicule pour alimenter R-2024-008 (la règle filtre
     * elle-même les indispos qui croisent l'année et les contrats
     * taxables — voir `R2024_008_ReductiveUnavailability::evaluate()`).
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, list<Unavailability>>
     */
    public function loadUnavailabilitiesByVehicle(array $vehicleIds): array
    {
        $byVehicle = [];
        foreach ($vehicleIds as $vehicleId) {
            $byVehicle[$vehicleId] = $this->unavailabilityRepository
                ->findForVehicle($vehicleId)
                ->all();
        }

        return $byVehicle;
    }

    /**
     * Liste des dates ISO occupées par un véhicule sur une période —
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
     * Dates ISO d'un couple sur l'année — preview taxes (incrément
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
     * Densité par véhicule × semaine ISO de l'année — heatmap planning.
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
     * Nombre total de jours-véhicule occupés sur l'année (KPI Dashboard).
     */
    public function countContractDaysForYear(int $year): int
    {
        $total = 0;
        foreach ($this->repository->findActiveForYear($year) as $contract) {
            $total += count($contract->expandToDaysInYear($year));
        }

        return $total;
    }

    /**
     * Contrats du véhicule chevauchant la fenêtre [start, end] —
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
