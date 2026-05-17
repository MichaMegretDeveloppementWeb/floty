<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import DashboardEvolutionChart from './partials/DashboardEvolutionChart.vue';
import DashboardEvolutionChartSkeleton from './partials/DashboardEvolutionChartSkeleton.vue';
import DashboardKpiCardSkeleton from './partials/DashboardKpiCardSkeleton.vue';
import DashboardKpiFiscalCards from './partials/DashboardKpiFiscalCards.vue';
import DashboardKpiRecettesCard from './partials/DashboardKpiRecettesCard.vue';
import DashboardPendingTasksRow from './partials/DashboardPendingTasksRow.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

const props = defineProps<{
    /** 4 KPIs fiscaux YTD · deferred · pipeline fiscal 1 année. */
    kpis?: App.Data.User.Dashboard.DashboardKpiData;
    /** Carte recettes · deferred avec les autres KPIs. */
    kpisRecettes?: App.Data.User.Dashboard.DashboardKpiRecettesData;
    /** Tâches en attente · deferred. */
    pendingTasks?: App.Data.User.Dashboard.DashboardPendingTasksData;
    /**
     * Onglet par défaut du graphique Évolution (Jours-véhicule) ·
     * deferred avec la vague KPIs. Les 3 autres onglets sont
     * `Inertia::optional` et chargés au clic d'onglet (doctrine
     * `feedback_lazy_tab_loading`).
     */
    historyJoursVehicule?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    historyContracts?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    historyTaxes?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    historyRecettes?: App.Data.User.Dashboard.DashboardHistoryPointData[];
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
}>();
void props;
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

            <!--
                Évolution · toujours visible. Le 1er onglet (Jours-véhicule)
                est `Inertia::defer` · skeleton chart pendant le 1er fetch.
                Les 3 autres onglets sont `Inertia::optional` et déclenchent
                un `router.reload({only: [...]})` au clic depuis le chart.
            -->
            <Deferred data="historyJoursVehicule">
                <template #fallback>
                    <DashboardEvolutionChartSkeleton />
                </template>
                <DashboardEvolutionChart
                    :history-jours-vehicule="historyJoursVehicule"
                    :history-contracts="historyContracts"
                    :history-taxes="historyTaxes"
                    :history-recettes="historyRecettes"
                />
            </Deferred>

            <Deferred data="pendingTasks">
                <template #fallback>
                    <div class="mt-10 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- 2 panels (Déclarations + Factures) · header icon + titre + 5 rows -->
                        <article
                            v-for="panelIdx in 2"
                            :key="panelIdx"
                            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5"
                            aria-busy="true"
                        >
                            <header class="flex items-center gap-3">
                                <Skeleton class="h-9 w-9 shrink-0 rounded-lg" />
                                <div class="flex flex-1 flex-col gap-1.5">
                                    <Skeleton class="h-3 w-40 rounded" />
                                    <Skeleton class="h-4 w-24 rounded" />
                                </div>
                            </header>
                            <div class="flex flex-col gap-2">
                                <Skeleton
                                    v-for="rowIdx in 4"
                                    :key="rowIdx"
                                    class="h-8 rounded"
                                />
                            </div>
                        </article>
                    </div>
                </template>
                <DashboardPendingTasksRow :tasks="pendingTasks!" />
            </Deferred>

            <QuickLinksGrid />
        </div>
    </UserLayout>
</template>
