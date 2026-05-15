<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardPendingDeclarationItemData;
use App\Data\User\Dashboard\DashboardPendingInvoiceItemData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrège les items en attente (déclarations + factures) sur toute la
 * flotte multi-entreprises pour le Dashboard (Phase 13 D5.15 ·
 * réécrit en D5.15.3 pour batch SQL).
 *
 * **Pourquoi un service dédié** · le `DashboardStatsService` doit
 * rester focalisé sur les KPI/heatmap/évolution. La logique
 * d'agrégation multi-entreprises des items pending mérite son propre
 * service · testable isolément, performant grâce à un chemin batch
 * dédié.
 *
 * **Stratégie batch (D5.15.3)** · au lieu d'itérer les entreprises et
 * d'appeler les resolvers `pendingForCompany()` (qui font N appels à
 * `BillingBreakdownService::byCompanyForYear` · ~6 SQL/appel),
 * l'aggregator charge en **5 SQL globales** ·
 *
 * 1. liste entreprises actives
 * 2. tous les contrats non supprimés (company_id, vehicle_id, start_date, end_date)
 * 3. toutes les déclarations fiscales non supprimées
 * 4. tous les vehicle_yearly_pricings pertinents
 * 5. toutes les invoices existantes (company_id, fiscal_year, month)
 *
 * Puis dérive les états et compte les mois manquants en PHP. Pour
 * une flotte de 5 entreprises · de 38 SQL → 5 SQL.
 *
 * **Simplifications acceptées pour le dashboard** ·
 * - Pour les déclarations en `Draft` sans predecessor, l'état est
 *   toujours résolu en `DraftPending` (CTA · « Continuer la revue »).
 *   La distinction fine avec `DraftReadyToGenerate` (CTA · « Générer »)
 *   nécessiterait un appel à `RiskDetection::detectClusters()` par
 *   (company, year) · trop coûteux pour un dashboard top 5. Le user
 *   accède au vrai CTA en cliquant sur la ligne (fiche entreprise).
 * - `pendingClustersCount` toujours à 0 dans le DTO Dashboard (non
 *   affiché sur la carte).
 */
final readonly class DashboardPendingTasksAggregator
{
    private const TOP_ITEMS = 5;

    public function __construct(
        private CompanyReadRepositoryInterface $companies,
    ) {}

    public function aggregate(): DashboardPendingTasksData
    {
        $companies = $this->companies->findAllForOptions();
        if ($companies->isEmpty()) {
            return DashboardPendingTasksData::noPending();
        }

        $companyIds = $companies->pluck('id')->map(static fn ($v): int => (int) $v)->all();

        $contracts = DB::table('contracts')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->select(['id', 'company_id', 'vehicle_id', 'start_date', 'end_date'])
            ->get();

        if ($contracts->isEmpty()) {
            return DashboardPendingTasksData::noPending();
        }

        $yearsByCompany = $this->buildYearsByCompany($contracts);
        $contractsByCompany = $contracts->groupBy('company_id');

        $declsByKey = $this->loadDeclarations($companyIds);
        $pricingByKey = $this->loadPricings($contracts->pluck('vehicle_id')->unique()->all());
        $invoiceByKey = $this->loadExistingInvoices($companyIds);

        $now = CarbonImmutable::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $declItems = [];
        $invItems = [];

        foreach ($companies as $company) {
            $companyId = (int) $company->id;
            $companyYears = array_keys($yearsByCompany[$companyId] ?? []);
            sort($companyYears);

            $companyContracts = $contractsByCompany->get($companyId, collect());

            foreach ($companyYears as $year) {
                $declItem = $this->buildDeclarationItem(
                    $company,
                    $companyId,
                    $year,
                    $declsByKey,
                    $now,
                    $currentYear,
                );
                if ($declItem !== null) {
                    $declItems[] = $declItem;
                }

                $invItem = $this->buildInvoiceItem(
                    $company,
                    $companyId,
                    $year,
                    $companyContracts,
                    $pricingByKey,
                    $invoiceByKey,
                    $currentYear,
                    $currentMonth,
                );
                if ($invItem !== null) {
                    $invItems[] = $invItem;
                }
            }
        }

        usort($declItems, static function (
            DashboardPendingDeclarationItemData $a,
            DashboardPendingDeclarationItemData $b,
        ): int {
            if ($a->isOverdue !== $b->isOverdue) {
                return $a->isOverdue ? -1 : 1;
            }

            return [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode];
        });

        usort($invItems, static fn (
            DashboardPendingInvoiceItemData $a,
            DashboardPendingInvoiceItemData $b,
        ): int => [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode]);

        $invMonthlyTotal = array_sum(array_map(
            static fn (DashboardPendingInvoiceItemData $item): int => $item->missingInvoicesCount,
            $invItems,
        ));

        return new DashboardPendingTasksData(
            pendingDeclarationsCount: count($declItems),
            pendingDeclarations: array_slice($declItems, 0, self::TOP_ITEMS),
            pendingInvoicesMonthlyTotal: $invMonthlyTotal,
            pendingInvoices: array_slice($invItems, 0, self::TOP_ITEMS),
        );
    }

    /**
     * Construit la map `[companyId => [year => true]]` représentant la
     * plage d'années couvertes par les contrats de chaque entreprise.
     *
     * @param  Collection<int, \stdClass>  $contracts
     * @return array<int, array<int, true>>
     */
    private function buildYearsByCompany($contracts): array
    {
        $map = [];
        foreach ($contracts as $c) {
            $startY = (int) substr((string) $c->start_date, 0, 4);
            $endY = (int) substr((string) $c->end_date, 0, 4);
            for ($y = $startY; $y <= $endY; $y++) {
                $map[(int) $c->company_id][$y] = true;
            }
        }

        return $map;
    }

    /**
     * Charge toutes les déclarations actives indexées par
     * `"{companyId}|{fiscalYear}"` (newest first via order DESC).
     *
     * @param  list<int>  $companyIds
     * @return array<string, list<object>>
     */
    private function loadDeclarations(array $companyIds): array
    {
        $rows = DB::table('fiscal_declarations')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->select([
                'id',
                'company_id',
                'fiscal_year',
                'status',
                'is_obsolete',
                'obsolete_at',
                'obsolete_reasons',
                'superseded_by_id',
            ])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $key = $row->company_id.'|'.$row->fiscal_year;
            $indexed[$key][] = $row;
        }

        return $indexed;
    }

    /**
     * Charge tous les tarifs annuels indexés par `"{vehicleId}|{year}"`.
     *
     * @param  list<int>  $vehicleIds
     * @return array<string, true>
     */
    private function loadPricings(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $rows = DB::table('vehicle_yearly_pricings')
            ->whereIn('vehicle_id', $vehicleIds)
            ->select(['vehicle_id', 'year'])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->vehicle_id.'|'.$row->year] = true;
        }

        return $indexed;
    }

    /**
     * Charge toutes les factures existantes indexées par
     * `"{companyId}|{year}|{month}"`.
     *
     * @param  list<int>  $companyIds
     * @return array<string, true>
     */
    private function loadExistingInvoices(array $companyIds): array
    {
        $rows = DB::table('invoices')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->select(['company_id', 'year', 'month'])
            ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->company_id.'|'.$row->year.'|'.$row->month] = true;
        }

        return $indexed;
    }

    /**
     * Construit un item « déclaration en attente » pour une (company,
     * year) donnée, ou null si l'année n'est pas pending.
     *
     * @param  array<string, list<object>>  $declsByKey
     */
    private function buildDeclarationItem(
        object $company,
        int $companyId,
        int $year,
        array $declsByKey,
        CarbonImmutable $now,
        int $currentYear,
    ): ?DashboardPendingDeclarationItemData {
        $declList = $declsByKey[$companyId.'|'.$year] ?? [];

        [$head, $predecessor] = $this->findHeadAndPredecessor($declList);
        $state = $this->deriveState($head, $predecessor);

        if ($state === DeclarationLifecycleState::GeneratedActive) {
            return null;
        }
        if ($state === DeclarationLifecycleState::Untouched && $year >= $currentYear) {
            return null;
        }

        $deadline = sprintf('%04d-04-30', $year + 1);
        $isOverdue = $now->isAfter(CarbonImmutable::parse($deadline));

        [$obsoleteSinceDate, $obsoleteReasonsCount] = $this->resolveObsoleteContext($head, $predecessor, $state);

        return new DashboardPendingDeclarationItemData(
            companyId: $companyId,
            companyShortCode: (string) $company->short_code,
            companyLegalName: (string) $company->legal_name,
            fiscalYear: $year,
            deadline: $deadline,
            isOverdue: $isOverdue,
            state: $state,
            currentDeclarationId: $head !== null ? (int) $head->id : null,
            pendingClustersCount: 0,
            obsoleteSinceDate: $obsoleteSinceDate,
            obsoleteReasonsCount: $obsoleteReasonsCount,
        );
    }

    /**
     * Construit un item « factures en attente » pour une (company,
     * year) donnée, ou null s'il n'y a pas de mois manquants.
     *
     * @param  Collection<int, \stdClass>  $companyContracts
     * @param  array<string, true>  $pricingByKey
     * @param  array<string, true>  $invoiceByKey
     */
    private function buildInvoiceItem(
        object $company,
        int $companyId,
        int $year,
        $companyContracts,
        array $pricingByKey,
        array $invoiceByKey,
        int $currentYear,
        int $currentMonth,
    ): ?DashboardPendingInvoiceItemData {
        $maxMonth = $year < $currentYear ? 12 : $currentMonth - 1;
        if ($maxMonth < 1) {
            return null;
        }
        if ($year > $currentYear) {
            return null;
        }

        $missingCount = 0;
        for ($month = 1; $month <= $maxMonth; $month++) {
            $monthStart = CarbonImmutable::create($year, $month, 1);
            $monthStartStr = $monthStart->toDateString();
            $monthEndStr = $monthStart->endOfMonth()->toDateString();

            $vehicleIdsInMonth = [];
            foreach ($companyContracts as $c) {
                $cStart = (string) $c->start_date;
                $cEnd = (string) $c->end_date;
                // Normalize datetime strings (e.g. "2024-03-01 00:00:00") to dates
                $cStart = substr($cStart, 0, 10);
                $cEnd = substr($cEnd, 0, 10);

                if ($cStart <= $monthEndStr && $cEnd >= $monthStartStr) {
                    $vehicleIdsInMonth[(int) $c->vehicle_id] = true;
                }
            }
            if ($vehicleIdsInMonth === []) {
                continue;
            }

            $hasAllPricing = true;
            foreach (array_keys($vehicleIdsInMonth) as $vid) {
                if (! isset($pricingByKey[$vid.'|'.$year])) {
                    $hasAllPricing = false;
                    break;
                }
            }
            if (! $hasAllPricing) {
                continue;
            }

            if (isset($invoiceByKey[$companyId.'|'.$year.'|'.$month])) {
                continue;
            }

            $missingCount++;
        }

        if ($missingCount <= 0) {
            return null;
        }

        return new DashboardPendingInvoiceItemData(
            companyId: $companyId,
            companyShortCode: (string) $company->short_code,
            companyLegalName: (string) $company->legal_name,
            fiscalYear: $year,
            missingInvoicesCount: $missingCount,
        );
    }

    /**
     * Trouve la « head » de la chaîne (superseded_by_id IS NULL) et son
     * éventuel predecessor (autre déclaration de la même année avec
     * `superseded_by_id = head.id`).
     *
     * @param  list<object>  $declList
     * @return array{0: ?object, 1: ?object}
     */
    private function findHeadAndPredecessor(array $declList): array
    {
        $head = null;
        foreach ($declList as $d) {
            if ($d->superseded_by_id === null) {
                $head = $d;
                break;
            }
        }

        $predecessor = null;
        if ($head !== null) {
            foreach ($declList as $d) {
                if ($d->superseded_by_id !== null && (int) $d->superseded_by_id === (int) $head->id) {
                    $predecessor = $d;
                    break;
                }
            }
        }

        return [$head, $predecessor];
    }

    /**
     * Mappe (head, predecessor) → DeclarationLifecycleState. Aligné sur
     * {@see DeclarationLifecycleResolver::deriveState}
     * sauf · pour Draft sans predecessor on retourne toujours
     * `DraftPending` (cf. PHPDoc de la classe).
     */
    private function deriveState(?object $head, ?object $predecessor): DeclarationLifecycleState
    {
        if ($head === null) {
            return DeclarationLifecycleState::Untouched;
        }

        $hasPredecessor = $predecessor !== null;
        $status = (string) $head->status;

        if ($status === 'draft') {
            return $hasPredecessor
                ? DeclarationLifecycleState::RegenerationInProgress
                : DeclarationLifecycleState::DraftPending;
        }

        if ($status === 'deferred') {
            return $hasPredecessor
                ? DeclarationLifecycleState::DeferredRegeneration
                : DeclarationLifecycleState::Deferred;
        }

        // generated
        return (int) $head->is_obsolete === 1
            ? DeclarationLifecycleState::GeneratedObsoleteOrphan
            : DeclarationLifecycleState::GeneratedActive;
    }

    /**
     * Pour les états S6/S7 (GeneratedObsoleteOrphan / RegenerationInProgress
     * / DeferredRegeneration), extrait `obsolete_at` et le nombre de
     * motifs depuis la déclaration porteuse (head pour S6, predecessor
     * pour S7).
     *
     * @return array{0: ?string, 1: int}
     */
    private function resolveObsoleteContext(
        ?object $head,
        ?object $predecessor,
        DeclarationLifecycleState $state,
    ): array {
        $source = match ($state) {
            DeclarationLifecycleState::GeneratedObsoleteOrphan => $head,
            DeclarationLifecycleState::RegenerationInProgress,
            DeclarationLifecycleState::DeferredRegeneration => $predecessor,
            default => null,
        };

        if ($source === null) {
            return [null, 0];
        }

        $rawDate = $source->obsolete_at !== null ? substr((string) $source->obsolete_at, 0, 10) : null;
        $reasons = $source->obsolete_reasons;
        if (is_string($reasons)) {
            $reasons = json_decode($reasons, true);
        }
        $count = is_array($reasons) ? count($reasons) : 0;

        return [$rawDate, $count];
    }
}
