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
    /** YTD fiscal KPIs (Inertia::defer). */
    kpis?: App.Data.User.Dashboard.DashboardKpiData;
    /** Revenue KPI (Inertia::defer). */
    kpisRecettes?: App.Data.User.Dashboard.DashboardKpiRecettesData;
    /** Pending tasks (Inertia::defer). */
    pendingTasks?: App.Data.User.Dashboard.DashboardPendingTasksData;
    /** Default chart tab (Inertia::defer); siblings load lazily per-tab. */
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
