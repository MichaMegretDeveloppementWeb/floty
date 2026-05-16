<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import type { HeatmapCosts } from '@/Components/Features/Planning/Heatmap/types';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import { useUserPlanningIndex } from '@/Composables/Planning/Index/useUserPlanningIndex';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapVehicleData[];
    companies: App.Data.User.Company.CompanyOptionData[];
    /**
     * Coûts fiscaux par véhicule, servis en `Inertia::defer` côté
     * controller (chantier perf 2026-05-16) · `undefined` au mount
     * initial, hydraté à la 2ᵉ RTT déclenchée auto par Inertia.
     */
    costs?: HeatmapCosts;
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 5).
     */
    yearScope: App.Data.Shared.YearScopeData;
}>();

// Ref local miroir de `props.costs` qui pilote la heatmap. On la reset
// à `undefined` au changement d'année AVANT le reload pour forcer les
// skeletons immédiatement (sinon les valeurs de l'année précédente
// resteraient affichées ~700 ms le temps de la 2ᵉ RTT · UX trompeuse).
// Cf. consigne mémoire `feedback_inertia_defer_with_partial_reload`.
const localCosts = ref<HeatmapCosts | undefined>(props.costs);

watch(
    () => props.costs,
    (next) => {
        localCosts.value = next;
    },
);

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['vehicles', 'companies', 'selectedYear'],
    {
        // Enchaîné après le visit year-change · l'URL pointe désormais
        // sur la nouvelle année, le reload `costs` recalcule sur la
        // bonne année. Skeletons visibles entre les 2 RTT.
        onSuccess: () => {
            localCosts.value = undefined;
            router.reload({ only: ['costs'] });
        },
    },
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
            <div class="flex flex-col gap-3">
                <PageHeader :fiscal-year="selectedYear" />
                <div class="flex justify-end">
                    <InlineYearSelector
                        id="planning-year"
                        v-model="yearModel"
                        :options="yearOptions"
                    />
                </div>
            </div>

            <Heatmap
                :vehicles="vehicles"
                :costs="localCosts"
                :fiscal-year="selectedYear"
                @cell-click="(p) => week.open(p.vehicleId, p.week, selectedYear)"
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
