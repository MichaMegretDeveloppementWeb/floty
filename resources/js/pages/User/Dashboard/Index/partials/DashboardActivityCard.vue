<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';
import Last30DaysHeatmap from './Last30DaysHeatmap.vue';

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

        <Last30DaysHeatmap :vehicles="activity.last30DaysHeatmap" />
    </Card>
</template>
