<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import type {
    HeatmapFullYearCosts,
    HeatmapRealCosts,
} from '@/Components/Features/Planning/Heatmap/types';
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
     * Coûts pleine année théoriques · `Inertia::defer` group "fast"
     * (chantier perf Étape 3 · 2026-05-17). Cachés · hydratation
     * rapide (~50 ms warm). Cellule « Taxe pleine » à gauche.
     */
    fullYearCosts?: HeatmapFullYearCosts;
    /**
     * Coût annuel dû réel · `Inertia::defer` group "slow" (non caché,
     * ~250 ms). Cellule « €XXXX · N j » à droite.
     */
    realCosts?: HeatmapRealCosts;
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 5).
     */
    yearScope: App.Data.Shared.YearScopeData;
}>();

// Refs locales miroirs des 2 props defer · reset à `undefined` au
// changement d'année AVANT le reload pour forcer les skeletons
// immédiatement (sinon valeurs année précédente affichées ~700 ms le
// temps de la RTT · UX trompeuse). Cf. mémoire
// `feedback_inertia_defer_with_partial_reload`.
const localFullYearCosts = ref<HeatmapFullYearCosts | undefined>(props.fullYearCosts);
const localRealCosts = ref<HeatmapRealCosts | undefined>(props.realCosts);

watch(
    () => props.fullYearCosts,
    (next) => {
        localFullYearCosts.value = next;
    },
);
watch(
    () => props.realCosts,
    (next) => {
        localRealCosts.value = next;
    },
);

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['vehicles', 'companies', 'selectedYear'],
    {
        // Enchaîné après le visit year-change · l'URL pointe désormais
        // sur la nouvelle année, les 2 reload recalculent sur la bonne
        // année · 2 fetchs parallèles via les groups defer "fast" + "slow".
        onSuccess: () => {
            localFullYearCosts.value = undefined;
            localRealCosts.value = undefined;
            router.reload({ only: ['fullYearCosts'] });
            router.reload({ only: ['realCosts'] });
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
                :full-year-costs="localFullYearCosts"
                :real-costs="localRealCosts"
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
