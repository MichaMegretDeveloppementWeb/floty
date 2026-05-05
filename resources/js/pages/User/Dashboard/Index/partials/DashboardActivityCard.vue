<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';
import Last30DaysHeatmap from './Last30DaysHeatmap.vue';
import TopExpensiveVehiclesCard from './TopExpensiveVehiclesCard.vue';

defineProps<{
    activity: App.Data.User.Dashboard.DashboardActivityData;
}>();
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Activité courante
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    État de la flotte sur les 30 derniers jours
                </p>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Last30DaysHeatmap :vehicles="activity.last30DaysHeatmap" />
            </div>
            <div class="xl:col-span-1">
                <TopExpensiveVehiclesCard :vehicles="activity.topExpensiveVehicles" />
            </div>
        </div>
    </Card>
</template>
