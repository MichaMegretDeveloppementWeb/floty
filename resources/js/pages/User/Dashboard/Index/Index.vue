<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DashboardActivityCard from './partials/DashboardActivityCard.vue';
import DashboardEvolutionChart from './partials/DashboardEvolutionChart.vue';
import DashboardKpiCards from './partials/DashboardKpiCards.vue';
import DashboardPendingTasksRow from './partials/DashboardPendingTasksRow.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

defineProps<{
    /** Lentille Présent — KPIs YTD + comparaison vs même période Y-1. */
    kpis: App.Data.User.Dashboard.DashboardKpiData;
    /** Lentille Évolution — historique multi-années pour graphique barres. */
    history: App.Data.User.Dashboard.DashboardYearHistoryData[];
    /** Lentille Exploration — heatmap 30j flotte + top véhicules coûteux. */
    activity: App.Data.User.Dashboard.DashboardActivityData;
    /** Compteurs tâches en attente (placeholders MVP). */
    pendingTasks: App.Data.User.Dashboard.DashboardPendingTasksData;
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

            <QuickLinksGrid />

            <DashboardEvolutionChart :history="history" />

            <DashboardActivityCard :activity="activity" />

            <DashboardPendingTasksRow :tasks="pendingTasks" />
        </div>
    </UserLayout>
</template>
