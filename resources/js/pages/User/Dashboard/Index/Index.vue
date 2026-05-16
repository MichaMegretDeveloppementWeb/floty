<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DashboardEvolutionChart from './partials/DashboardEvolutionChart.vue';
import DashboardKpiCards from './partials/DashboardKpiCards.vue';
import DashboardPendingTasksRow from './partials/DashboardPendingTasksRow.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

defineProps<{
    /** Lentille Présent · KPIs YTD + comparaison vs même période Y-1. */
    kpis: App.Data.User.Dashboard.DashboardKpiData;
    /**
     * Lentille Évolution · historique multi-années pour graphique barres.
     * S2.3 · deferred via `Inertia::defer()` côté backend · arrive dans
     * une 2e requête asynchrone après le mount initial. Skeleton rendu
     * via `<Deferred data="history">` le temps du fetch.
     */
    history?: App.Data.User.Dashboard.DashboardYearHistoryData[];
    /**
     * Tâches en attente · top 5 déclarations et factures + compteurs totaux.
     * S2.3 · deferred (cf. ci-dessus).
     */
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
            <PageHeader :fiscal-year="kpis.year" />

            <DashboardKpiCards :kpis="kpis" />

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
