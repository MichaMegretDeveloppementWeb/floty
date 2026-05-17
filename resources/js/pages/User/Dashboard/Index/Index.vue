<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DashboardEvolutionChart from './partials/DashboardEvolutionChart.vue';
import DashboardKpiCardSkeleton from './partials/DashboardKpiCardSkeleton.vue';
import DashboardKpiFiscalCards from './partials/DashboardKpiFiscalCards.vue';
import DashboardKpiRecettesCard from './partials/DashboardKpiRecettesCard.vue';
import DashboardPendingTasksRow from './partials/DashboardPendingTasksRow.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

defineProps<{
    /**
     * Lentille Présent · 4 KPIs fiscaux YTD + comparaison Y-1.
     * Deferred (chantier perf Dashboard 2026-05-17) · arrive dans une
     * 2e requête asynchrone après le mount. Skeletons par carte le
     * temps du fetch.
     */
    kpis?: App.Data.User.Dashboard.DashboardKpiData;
    /**
     * Carte recettes locatives isolée · deferred indépendamment des
     * KPIs fiscaux pour que la grille KPIs apparaisse sans attendre
     * les ~60 queries SQL de `BillingBreakdownService`.
     */
    kpisRecettes?: App.Data.User.Dashboard.DashboardKpiRecettesData;
    /**
     * Lentille Évolution · historique multi-années pour graphique barres.
     * Deferred · arrive dans une 3e requête. Skeleton rendu via
     * `<Deferred data="history">` le temps du fetch.
     */
    history?: App.Data.User.Dashboard.DashboardYearHistoryData[];
    /** Tâches en attente · top 5 déclarations et factures + compteurs totaux. */
    pendingTasks?: App.Data.User.Dashboard.DashboardPendingTasksData;
    /** Année résolue par le backend (sert au PageHeader uniquement). */
    selectedYear: number;
    /** Scope d'années dynamique (chantier η Phase 5). */
    yearScope: App.Data.Shared.YearScopeData;
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <UserLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :fiscal-year="selectedYear" />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Deferred data="kpis">
                    <template #fallback>
                        <DashboardKpiCardSkeleton />
                        <DashboardKpiCardSkeleton />
                        <DashboardKpiCardSkeleton />
                    </template>
                    <DashboardKpiFiscalCards :kpis="kpis!" />
                </Deferred>

                <Deferred data="kpisRecettes">
                    <template #fallback>
                        <DashboardKpiCardSkeleton />
                    </template>
                    <DashboardKpiRecettesCard :recettes="kpisRecettes!" />
                </Deferred>
            </div>

            <Deferred data="history">
                <template #fallback>
                    <div class="rounded-xl border border-slate-200 bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="space-y-2">
                                <div class="h-4 w-40 animate-pulse rounded bg-slate-200" />
                                <div class="h-3 w-56 animate-pulse rounded bg-slate-100" />
                            </div>
                            <div class="h-8 w-72 animate-pulse rounded-lg bg-slate-100" />
                        </div>
                        <div class="h-[280px] animate-pulse rounded-lg bg-slate-50" />
                    </div>
                </template>
                <DashboardEvolutionChart :history="history!" />
            </Deferred>

            <Deferred data="pendingTasks">
                <template #fallback>
                    <div class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="h-48 animate-pulse rounded-xl border border-slate-200 bg-slate-50" />
                        <div class="h-48 animate-pulse rounded-xl border border-slate-200 bg-slate-50" />
                    </div>
                </template>
                <DashboardPendingTasksRow :tasks="pendingTasks!" />
            </Deferred>

            <QuickLinksGrid />
        </div>
    </UserLayout>
</template>
