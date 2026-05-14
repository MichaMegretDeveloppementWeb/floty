<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardPendingDeclarationItemData;
use App\Data\User\Dashboard\DashboardPendingInvoiceItemData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\Services\Billing\PendingInvoicesResolver;
use App\Services\Fiscal\Declaration\PendingDeclarationsResolver;

/**
 * Agrège les items en attente (déclarations + factures) sur toute la
 * flotte multi-entreprises pour le Dashboard (Phase 13 D5.15).
 *
 * **Pourquoi un service dédié** · le `DashboardStatsService` doit
 * rester focalisé sur les KPI/heatmap/évolution. La logique
 * d'agrégation multi-entreprises des items pending mérite son propre
 * service · testable isolément, réutilisable si demain on veut une
 * page « Centre d'action » consolidée.
 *
 * **Stratégie d'agrégation V1** · itère sur les entreprises actives et
 * délègue aux resolvers déjà testés (`PendingDeclarationsResolver`,
 * `PendingInvoicesResolver`). Chaque resolver expose `pendingForCompany()`
 * qui contient sa propre logique (lifecycle state pour déclarations,
 * mois éligibles pour factures). Le service enrichit chaque entrée du
 * contexte entreprise (companyId / shortCode / legalName) pour rendu
 * autosuffisant côté Dashboard.
 *
 * **Note perf** · pour V1 (faible volumétrie · 5-50 entreprises), la
 * boucle est acceptable. Si volumétrie ↑ ou perf dégradée, factoriser
 * via une vraie query batch SQL au niveau des resolvers (méthode
 * future `pendingForAllCompanies()`). Mesurer avant d'optimiser.
 *
 * **Tri par urgence** · les items overdue (échéance dépassée) en
 * premier, puis ordre chronologique ascendant (plus ancien = plus
 * urgent à traiter).
 *
 * **Top 5** · le Dashboard affiche les 5 items les plus urgents par
 * domaine. Le compteur total (`*Count`) reste exhaustif pour le badge
 * « Voir les N autres ».
 */
final readonly class DashboardPendingTasksAggregator
{
    private const TOP_ITEMS = 5;

    public function __construct(
        private CompanyReadRepositoryInterface $companies,
        private PendingDeclarationsResolver $declarationsResolver,
        private PendingInvoicesResolver $invoicesResolver,
    ) {}

    public function aggregate(): DashboardPendingTasksData
    {
        $allCompanies = $this->companies->findAllForOptions();

        $declItems = [];
        $invItems = [];

        foreach ($allCompanies as $company) {
            foreach ($this->declarationsResolver->pendingForCompany($company->id) as $pending) {
                $declItems[] = new DashboardPendingDeclarationItemData(
                    companyId: $company->id,
                    companyShortCode: $company->short_code,
                    companyLegalName: $company->legal_name,
                    fiscalYear: $pending->fiscalYear,
                    deadline: $pending->deadline,
                    isOverdue: $pending->isOverdue,
                    state: $pending->state,
                    currentDeclarationId: $pending->currentDeclarationId,
                    pendingClustersCount: $pending->pendingClustersCount,
                    obsoleteSinceDate: $pending->obsoleteSinceDate,
                    obsoleteReasonsCount: $pending->obsoleteReasonsCount,
                );
            }

            foreach ($this->invoicesResolver->pendingForCompany($company->id) as $pending) {
                $invItems[] = new DashboardPendingInvoiceItemData(
                    companyId: $company->id,
                    companyShortCode: $company->short_code,
                    companyLegalName: $company->legal_name,
                    fiscalYear: $pending->fiscalYear,
                    missingInvoicesCount: $pending->missingInvoicesCount,
                );
            }
        }

        // Tri urgence · overdue d'abord, puis année croissante (plus
        // ancien = plus urgent à traiter), puis label entreprise pour
        // stabilité du rendu.
        usort($declItems, static function (
            DashboardPendingDeclarationItemData $a,
            DashboardPendingDeclarationItemData $b,
        ): int {
            if ($a->isOverdue !== $b->isOverdue) {
                return $a->isOverdue ? -1 : 1;
            }

            return [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode];
        });

        // Factures · tri ascendant par année puis label entreprise. Pas
        // de notion d'overdue côté facturation V1 · le ressenti urgent
        // vient du nombre de mois cumulés (visible côté front).
        usort($invItems, static fn (
            DashboardPendingInvoiceItemData $a,
            DashboardPendingInvoiceItemData $b,
        ): int => [$a->fiscalYear, $a->companyShortCode] <=> [$b->fiscalYear, $b->companyShortCode]);

        // Total des factures mensuelles à générer toutes lignes
        // confondues · une ligne (entreprise, année) peut contenir
        // jusqu'à 12 factures mensuelles. Le compteur de lignes ne
        // suffit donc pas pour le header « N factures en attente ».
        $invMonthlyTotal = array_sum(array_map(
            static fn (DashboardPendingInvoiceItemData $item): int => $item->missingInvoicesCount,
            $invItems,
        ));

        return new DashboardPendingTasksData(
            pendingDeclarationsCount: count($declItems),
            pendingDeclarations: array_slice($declItems, 0, self::TOP_ITEMS),
            pendingInvoicesCount: count($invItems),
            pendingInvoicesMonthlyTotal: $invMonthlyTotal,
            pendingInvoices: array_slice($invItems, 0, self::TOP_ITEMS),
        );
    }
}
