<?php

declare(strict_types=1);

namespace App\Services\Contract;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\Billing\ContractBillingBreakdownData;
use App\Data\User\Company\CompanyContractsStatsData;
use App\Data\User\Contract\ContractData;
use App\Data\User\Contract\ContractDocumentData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Data\User\Contract\ContractListItemData;
use App\Data\User\Contract\ContractTaxBreakdownData;
use App\Data\User\Contract\ContractTaxYearBreakdownData;
use App\Data\User\Contract\PaginatedContractListData;
use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Fiscal\Declaration\DeclarationAggregatorFactory;
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
        private BillingBreakdownService $billingBreakdown,
        private RentalPriceCalculator $rentalPrice,
        private DeclarationAggregatorFactory $aggregatorFactory,
    ) {}

    /**
     * Récap facturation contrat-isolé (Phase 14.D V1.2 · extension).
     * Délègue à {@see App\Services\Billing\BillingBreakdownService::byContract}.
     *
     * Retourne `null` si le contrat est introuvable.
     */
    public function findContractBillingBreakdown(int $id): ?ContractBillingBreakdownData
    {
        $contract = $this->repository->findById($id);

        if ($contract === null) {
            return null;
        }

        return $this->billingBreakdown->byContract($contract);
    }

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
     * **Enrichissement hypothétique LCD** · pour les contrats de type
     * LCD, lance une 2e passe pipeline avec opt-out R-YYYY-021 sur le
     * contrat courant (mécanisme cluster requalification, cf.
     * {@see DeclarationAggregatorFactory}) pour calculer ce que le
     * contrat coûterait s'il était requalifié en LLD · vrai pipeline
     * (multi-VFC, multi-règle) au lieu d'une approximation linéaire.
     * Les valeurs sont injectées dans les `hypothetical*` du DTO Year.
     *
     * Coût payé UNIQUEMENT pour les LCD ET sur la page Show · l'Index
     * n'invoque pas cette méthode (cf. `costsForContractIds`).
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

        $nominal = $this->aggregator->contractTaxBreakdown($contract, $unavailabilities);

        // Pas un LCD · pas d'opt-out LCD pertinent, on retourne tel quel.
        if ($contract->contract_type !== ContractType::Lcd) {
            return $nominal;
        }

        return $this->enrichWithLcdHypothetical($contract, $unavailabilities, $nominal);
    }

    /**
     * Pour un contrat LCD · reconstruit le breakdown nominal en
     * peuplant les 3 champs `hypothetical*` de chaque année. Vrai
     * calcul via aggregator opt-out (un par année traversée · les
     * decorators sont year-scoped).
     *
     * Toujours peuplé pour les LCD, même si égal au nominal (cas du
     * véhicule hors champ fiscal qui reste à 0 € même requalifié). UI
     * affiche alors "0 € si requalifié en LLD" · l'utilisateur voit
     * clairement que le contrat resterait à 0 €, pas de mystère.
     *
     * @param  list<Unavailability>  $unavailabilities
     */
    private function enrichWithLcdHypothetical(
        Contract $contract,
        array $unavailabilities,
        ContractTaxBreakdownData $nominal,
    ): ContractTaxBreakdownData {
        $enrichedYears = [];

        foreach ($nominal->years as $year) {
            $optOutAggregator = $this->aggregatorFactory->buildFor($year->year, [$contract->id]);
            $optOutBreakdown = $optOutAggregator->contractTaxBreakdown($contract, $unavailabilities);

            $optOutYear = null;
            foreach ($optOutBreakdown->years as $oy) {
                if ($oy->year === $year->year) {
                    $optOutYear = $oy;
                    break;
                }
            }

            // Année hors registry · pas d'hypothétique calculable.
            if ($optOutYear === null) {
                $enrichedYears[] = $year;

                continue;
            }

            $enrichedYears[] = new ContractTaxYearBreakdownData(
                year: $year->year,
                daysInContractInYear: $year->daysInContractInYear,
                daysAssigned: $year->daysAssigned,
                daysInYear: $year->daysInYear,
                co2Method: $year->co2Method,
                pollutantCategory: $year->pollutantCategory,
                co2FullYearTariff: $year->co2FullYearTariff,
                pollutantsFullYearTariff: $year->pollutantsFullYearTariff,
                co2Due: $year->co2Due,
                pollutantsDue: $year->pollutantsDue,
                totalDue: $year->totalDue,
                appliedExemptions: $year->appliedExemptions,
                appliedRuleCodes: $year->appliedRuleCodes,
                appliedRules: $year->appliedRules,
                segments: $year->segments,
                hypotheticalCo2DueIfNoLcd: $optOutYear->co2Due,
                hypotheticalPollutantsDueIfNoLcd: $optOutYear->pollutantsDue,
                hypotheticalTotalDueIfNoLcd: $optOutYear->totalDue,
            );
        }

        return new ContractTaxBreakdownData(
            years: $enrichedYears,
            totalDue: $nominal->totalDue,
        );
    }

    /**
     * Index Contracts paginé server-side (cf. ADR-0020). Le repo gère
     * pagination + filtres + search + tri en SQL pure ; le service mappe
     * les models en DTO et enrichit avec coûts (totalTax + rentalPrice).
     *
     * Utilisé par les pages qui veulent les coûts inclus dans le payload
     * initial (ex. onglet Contrats sur la fiche Company). Pour
     * l'Index Contracts standalone, préférer {@see listPaginatedSlim}
     * + {@see costsForContractIds} en différé (chantier perf
     * 2026-05-16 Option 1).
     */
    public function listPaginated(ContractIndexQueryData $query): PaginatedContractListData
    {
        $paginator = $this->repository->paginateForIndex($query);
        $contracts = $paginator->items();

        // Batch unique pour les loyers de la page · évite le N+1
        // (`forContract` × 25 items × M mois lookups pricings).
        $rentalByContractId = $this->rentalPrice->forContracts($contracts);

        // Batch indispos par véhicule distinct · 1 query SQL au lieu
        // de N (alimente le vrai pipeline fiscal dans enrichContractDto).
        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contracts,
        )));
        $unavailabilitiesByVehicleId = $this->unavailabilityRepository
            ->findForVehicleIds($vehicleIds);

        // Prewarm VFC segments pour toutes les années traversées · évite
        // le N+1 query VFC dans la boucle contractTaxBreakdown.
        $this->prewarmVfcSegmentsForContracts($contracts);

        $items = array_map(
            fn (Contract $c): ContractListItemData => $this->enrichContractDto(
                $c,
                $rentalByContractId[$c->id] ?? null,
                $unavailabilitiesByVehicleId[$c->vehicle_id] ?? [],
            ),
            $contracts,
        );

        return new PaginatedContractListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Variante **slim** de {@see listPaginated} · ne calcule PAS les
     * coûts (`totalTax`, `rentalPrice` restent `null`). Le payload
     * initial est servi sans payer le pipeline fiscal · les coûts
     * arrivent dans une 2e requête Inertia::defer côté
     * `ContractController::index` qui appelle {@see costsForContractIds}.
     *
     * Doctrine `chargement-strict-par-ecran.md` · l'Index n'a pas
     * besoin des coûts pour s'afficher · skeleton ces 2 colonnes le
     * temps du fetch différé.
     *
     * **Gain mesuré** · ~210 ms cold sur 25 contrats / 21 véhicules
     * distincts (le pipeline fiscal `vehicleFullYearTax` n'est plus
     * appelé au render initial).
     */
    public function listPaginatedSlim(ContractIndexQueryData $query): PaginatedContractListData
    {
        $paginator = $this->repository->paginateForIndex($query);
        $contracts = $paginator->items();

        $items = array_map(
            static fn (Contract $c): ContractListItemData => ContractListItemData::fromModel($c),
            $contracts,
        );

        return new PaginatedContractListData(
            data: $items,
            meta: PaginationMetaData::fromPaginator($paginator),
        );
    }

    /**
     * Calcule la map des coûts (`totalTax`, `rentalPrice`) pour un
     * batch de contrats donnés par leurs IDs. Utilisé en
     * `Inertia::defer` côté `ContractController::index` pour remplir
     * les 2 colonnes de coûts après le render initial de l'Index.
     *
     * **Calcul fiscal** · délègue à {@see FleetFiscalAggregator::contractTaxBreakdown()}
     * - vrai pipeline par contrat, prend en compte les exonérations
     * (R-2024-021 LCD = 0 €), les chevauchements multi-VFC, les
     * changements de barème intra-année · strictement équivalent à
     * la valeur affichée sur la page Show contrat. **Ne pas utiliser
     * d'approximation `fullYearTax × jours/365`** · faux dès qu'il y
     * a une exonération ou une scission.
     *
     * Performance ·
     *   - Recharge les contrats avec relations en 1 query.
     *   - Batch indispos par véhicule distinct en 1 query.
     *   - Batch rental prices via `rentalPrice->forContracts()`.
     *
     * @param  list<int>  $contractIds
     * @return array<int, array{totalTax: float, rentalPrice: float|null}>
     */
    public function costsForContractIds(array $contractIds): array
    {
        if ($contractIds === []) {
            return [];
        }

        $contracts = $this->repository
            ->findByIdsWithRelations($contractIds)
            ->all();

        // Batch indispos · 1 query SQL pour tous les véhicules distincts.
        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contracts,
        )));
        $unavailabilitiesByVehicleId = $this->unavailabilityRepository
            ->findForVehicleIds($vehicleIds);

        // Batch rental prices · 1 query SQL pour tous les pricings.
        $rentalByContractId = $this->rentalPrice->forContracts($contracts);

        // Prewarm VFC segments pour toutes les années traversées · évite
        // le N+1 query VFC dans la boucle contractTaxBreakdown.
        $this->prewarmVfcSegmentsForContracts($contracts);

        $result = [];
        foreach ($contracts as $contract) {
            $totalTax = 0.0;
            try {
                // Vrai pipeline fiscal par contrat (multi-VFC, multi-règle,
                // exonération LCD R-2024-021, etc.) · strictement
                // équivalent au calcul de la page Show contrat.
                $breakdown = $this->aggregator->contractTaxBreakdown(
                    $contract,
                    $unavailabilitiesByVehicleId[$contract->vehicle_id] ?? [],
                );
                $totalTax = $breakdown->totalDue;
            } catch (\Throwable) {
                // Année hors registry fiscal · totalTax = 0.
            }

            $rentalCents = $rentalByContractId[$contract->id] ?? null;

            $result[$contract->id] = [
                'totalTax' => $totalTax,
                'rentalPrice' => $rentalCents === null ? null : $rentalCents / 100,
            ];
        }

        return $result;
    }

    /**
     * Phase 13 D5.10.L · enrichit le DTO de base avec `totalTax` et
     * `rentalPrice` calculés en direct. **Le totalTax utilise le vrai
     * pipeline fiscal** ({@see FleetFiscalAggregator::contractTaxBreakdown()})
     * pour rester strictement équivalent à la valeur affichée sur la
     * page Show contrat (prise en compte des exonérations LCD, des
     * chevauchements multi-VFC, etc.).
     *
     * `$preComputedRentalCents` : utilisé par les Index paginés qui ont
     * batché les loyers en amont · si `null`, fallback au lookup
     * individuel (utilisé sur fiche Contract isolée).
     *
     * `$preComputedVehicleUnavailabilities` : utilisé par les Index
     * paginés qui ont batché les indispos en amont (1 query unique pour
     * tous les véhicules distincts) · si `null`, fallback au lookup
     * individuel par véhicule.
     *
     * @param  list<Unavailability>|null  $preComputedVehicleUnavailabilities
     */
    private function enrichContractDto(
        Contract $contract,
        ?int $preComputedRentalCents = null,
        ?array $preComputedVehicleUnavailabilities = null,
    ): ContractListItemData {
        $base = ContractListItemData::fromModel($contract);

        $unavailabilities = $preComputedVehicleUnavailabilities
            ?? $this->unavailabilityRepository->findForVehicle($contract->vehicle_id)->all();

        $totalTax = 0.0;
        try {
            $breakdown = $this->aggregator->contractTaxBreakdown($contract, $unavailabilities);
            $totalTax = $breakdown->totalDue;
        } catch (\Throwable) {
            // Année hors registry fiscal · totalTax laissé à 0.
        }

        $rentalCents = $preComputedRentalCents ?? $this->rentalPrice->forContract($contract->id);
        $rentalPrice = $rentalCents === null ? null : $rentalCents / 100;

        return new ContractListItemData(
            id: $base->id,
            vehicleId: $base->vehicleId,
            vehicleLicensePlate: $base->vehicleLicensePlate,
            vehicleIsExited: $base->vehicleIsExited,
            companyId: $base->companyId,
            companyShortCode: $base->companyShortCode,
            companyLegalName: $base->companyLegalName,
            companyColor: $base->companyColor,
            drivers: $base->drivers,
            startDate: $base->startDate,
            endDate: $base->endDate,
            durationDays: $base->durationDays,
            contractType: $base->contractType,
            contractReference: $base->contractReference,
            totalTax: $totalTax,
            rentalPrice: $rentalPrice,
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
     * aucun contrat, retourne un tableau vide · les pills ne sont
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
     * passée en paramètre · utilisée par l'onglet Contrats de la fiche
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
            year: $query->year,
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
        $contractsAll = $contracts->all();
        $rentalByContractId = $this->rentalPrice->forContracts($contractsAll);

        // Batch indispos par véhicule distinct · 1 query SQL au lieu
        // de N (alimente le vrai pipeline fiscal dans enrichContractDto).
        $vehicleIds = array_values(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $contractsAll,
        )));
        $unavailabilitiesByVehicleId = $this->unavailabilityRepository
            ->findForVehicleIds($vehicleIds);

        // Prewarm VFC segments pour toutes les années traversées · évite
        // le N+1 query VFC dans la boucle contractTaxBreakdown.
        $this->prewarmVfcSegmentsForContracts($contractsAll);

        /** @var DataCollection<int, ContractListItemData> */
        return ContractListItemData::collect(
            $contracts->map(
                fn (Contract $c): ContractListItemData => $this->enrichContractDto(
                    $c,
                    $rentalByContractId[$c->id] ?? null,
                    $unavailabilitiesByVehicleId[$c->vehicle_id] ?? [],
                ),
            ),
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
     * Variante range de {@see loadContractsByPair} · charge tous les
     * contrats actifs d'un range d'années en **1 query SQL**, retourne
     * un map `year → ContractsByPair`. Chaque contrat est dispatché
     * dans toutes les années qu'il chevauche (un contrat 2023-2025
     * apparaît dans les pivots de 2023, 2024 et 2025).
     *
     * Cas d'usage · pages multi-années (Show Company Overview, Dashboard
     * history) qui itèrent N années · évite les N appels indépendants
     * à `loadContractsByPair($year)` · cf. F-11-001.
     *
     * @return array<int, ContractsByPair> · keyé par année
     */
    public function loadContractsByPairForYearRange(int $from, int $to): array
    {
        $contracts = $this->repository->findActiveForYearRange($from, $to);

        // Pré-calcule les bornes d'année une fois par contrat (start_date
        // et end_date sont des CarbonImmutable via le cast Eloquent).
        $byYear = [];
        for ($year = $from; $year <= $to; $year++) {
            $byYear[$year] = [];
        }

        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;

            $clampedFrom = max($startYear, $from);
            $clampedTo = min($endYear, $to);

            for ($year = $clampedFrom; $year <= $clampedTo; $year++) {
                $key = $contract->vehicle_id.'|'.$contract->company_id;
                $byYear[$year][$key] ??= [];
                $byYear[$year][$key][] = $contract;
            }
        }

        $result = [];
        foreach ($byYear as $year => $byPair) {
            $result[$year] = new ContractsByPair($byPair);
        }

        return $result;
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
     * par {@see AppServicesCompanyCompanyDetailService::detail()}.
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

        // S2.1 (plan optim perf 2026-05-15) · arithmétique semaine par
        // semaine via {@see Contract::daysByWeekInYear}. Sécurité dédup ·
        // la contrainte métier interdit deux contrats du même véhicule
        // sur les mêmes jours, donc sommation cross-contracts safe (le
        // `Set<date>` historique était défensif mais redondant).
        $byWeek = [];
        foreach ($contracts as $contract) {
            $companyId = $contract->company_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $byWeek[$week] ??= [];
                $byWeek[$week][$companyId] = ($byWeek[$week][$companyId] ?? 0) + $days;
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

        // S2.1 (plan optim perf 2026-05-15) · arithmétique semaine par
        // semaine via {@see Contract::daysByWeekInYear}. Sécurité dédup ·
        // la contrainte métier interdit deux contrats du même véhicule
        // sur les mêmes jours, donc sommation cross-contracts safe.
        $density = [];
        foreach ($contracts as $contract) {
            $vehicleId = $contract->vehicle_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $key = $vehicleId.'|'.$week;
                $density[$key] = ($density[$key] ?? 0) + $days;
            }
        }

        return $density;
    }

    /**
     * Variante de {@see loadWeekDensity} filtrée sur une entreprise
     * donnée - alimente la heatmap Vue Entreprise (chantier P1) où le
     * **chiffre cellule** ne reflète que les jours utilisés par cette
     * entreprise (la couleur reste pilotée par la densité globale).
     *
     * Clé = `"vehicleId|weekNumber"` ; valeur = nombre de jours occupés
     * par au moins un contrat de l'entreprise pour ce véhicule cette
     * semaine.
     *
     * @return array<string, int>
     */
    public function loadWeekDensityForCompany(int $year, int $companyId): array
    {
        $contracts = $this->repository->findActiveForYear($year);

        // S2.1 (plan optim perf 2026-05-15) · arithmétique semaine par
        // semaine via {@see Contract::daysByWeekInYear}. Filtre company
        // d'abord (skip rapide), puis sommation arithmétique safe (cf.
        // commentaire {@see loadWeekDensity}).
        $density = [];
        foreach ($contracts as $contract) {
            if ($contract->company_id !== $companyId) {
                continue;
            }

            $vehicleId = $contract->vehicle_id;
            foreach ($contract->daysByWeekInYear($year) as $week => $days) {
                $key = $vehicleId.'|'.$week;
                $density[$key] = ($density[$key] ?? 0) + $days;
            }
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
     * Prewarm `$vfcSegmentsCache` de l'aggregator pour tous les couples
     * `(vehicleId, year)` traversés par le batch de contrats. Une seule
     * query SQL `findEffectiveSegmentsForYearBatch` par année alimente
     * le cache · les `contractTaxBreakdown()` suivants consomment le
     * cache au lieu de re-fetcher les VFC un contrat à la fois.
     *
     * Gain · sur 25 contrats / 21 véhicules distincts sur 1 année,
     * passe de ~25 queries VFC à 1 (1 batch par année traversée).
     *
     * Multi-année · un contrat 2024→2025 prewarm les 2 années · la
     * méthode regroupe sans doublon avant d'appeler le prewarm.
     *
     * @param  list<Contract>  $contracts
     */
    private function prewarmVfcSegmentsForContracts(array $contracts): void
    {
        $vehiclesByYear = [];
        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;
            for ($year = $startYear; $year <= $endYear; $year++) {
                $vehiclesByYear[$year][$contract->vehicle_id] = $contract->vehicle;
            }
        }

        foreach ($vehiclesByYear as $year => $vehiclesById) {
            $this->aggregator->prewarmVfcSegmentsForVehicles(
                array_values($vehiclesById),
                $year,
            );
        }
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

        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

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
