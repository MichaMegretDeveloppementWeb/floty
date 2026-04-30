<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useUserPlanningIndex } from '@/Composables/Planning/Index/useUserPlanningIndex';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import PageHeader from './partials/PageHeader.vue';

defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapVehicleData[];
    companies: App.Data.User.Company.CompanyOptionData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const { week, onContractsCreated } = useUserPlanningIndex();
</script>

<template>
    <Head title="Vue d'ensemble" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <Heatmap
                :vehicles="vehicles"
                :fiscal-year="fiscalYear"
                @cell-click="(p) => week.open(p.vehicleId, p.week)"
            />
        </div>

        <WeekDrawer
            :open="week.drawerOpen.value"
            :week="week.weekData.value"
            :companies="companies"
            :fiscal-year="fiscalYear"
            @close="week.close"
            @contracts-created="onContractsCreated"
        />
    </UserLayout>
</template>
