<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useUserPlanningIndex } from '@/Composables/Planning/Index/useUserPlanningIndex';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapVehicleData[];
    companies: App.Data.User.Company.CompanyOptionData[];
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 5).
     */
    yearScope: App.Data.Shared.YearScopeData;
}>();

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['vehicles', 'companies', 'selectedYear'],
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

const { week, onContractsCreated } = useUserPlanningIndex();
</script>

<template>
    <Head title="Vue d'ensemble" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <PageHeader :fiscal-year="selectedYear" />
                <div class="flex flex-col gap-1">
                    <FieldLabel for="planning-year">Exercice</FieldLabel>
                    <SelectInput
                        id="planning-year"
                        v-model="yearModel"
                        :options="yearOptions"
                    />
                </div>
            </div>

            <Heatmap
                :vehicles="vehicles"
                :fiscal-year="selectedYear"
                @cell-click="(p) => week.open(p.vehicleId, p.week)"
            />
        </div>

        <WeekDrawer
            :open="week.drawerOpen.value"
            :week="week.weekData.value"
            :companies="companies"
            :fiscal-year="selectedYear"
            @close="week.close"
            @contracts-created="onContractsCreated"
        />
    </UserLayout>
</template>
