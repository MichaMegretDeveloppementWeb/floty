<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanningExportModal from '@/Components/Features/Planning/Export/PlanningExportModal.vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import type {
    HeatmapFullYearCosts,
    HeatmapMonthlyRentals,
    HeatmapRealCosts,
} from '@/Components/Features/Planning/Heatmap/types';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import { useUserPlanningIndex } from '@/Composables/Planning/Index/useUserPlanningIndex';
import { usePlanningTableView } from '@/Composables/Planning/usePlanningTableView';
import type { SortDirection } from '@/Composables/Shared/useLocalSortDirection';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapVehicleData[];
    companies: App.Data.User.Company.CompanyOptionData[];
    /** Theoretical full-year tax (Inertia::defer "fast", cached). */
    fullYearCosts?: HeatmapFullYearCosts;
    /** Actual yearly tax due (Inertia::defer "slow", uncached). */
    realCosts?: HeatmapRealCosts;
    /** Cross-company cumulative monthly rentals (Inertia::defer "rentals"). */
    monthlyRentals?: HeatmapMonthlyRentals;
    selectedYear: number;
    /** Current license-plate sort direction (?direction= URL param). */
    sortDirection: SortDirection;
    /** Dynamic year scope computed from active contracts. */
    yearScope: App.Data.Shared.YearScopeData;
    /** Years with coded fiscal rules · drives the "no fiscal rules" UI. */
    fiscalSupportedYears: number[];
}>();

// Local mirrors reset to undefined before reload so skeletons appear
// instantly on year change (avoids showing stale year values during RTT).
const localFullYearCosts = ref<HeatmapFullYearCosts | undefined>(
    props.fullYearCosts,
);
const localRealCosts = ref<HeatmapRealCosts | undefined>(props.realCosts);
const localMonthlyRentals = ref<HeatmapMonthlyRentals | undefined>(
    props.monthlyRentals,
);

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
watch(
    () => props.monthlyRentals,
    (next) => {
        localMonthlyRentals.value = next;
    },
);

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['vehicles', 'companies', 'selectedYear'],
    {
        onSuccess: () => {
            localFullYearCosts.value = undefined;
            localRealCosts.value = undefined;
            localMonthlyRentals.value = undefined;
            router.reload({ only: ['fullYearCosts'] });
            router.reload({ only: ['realCosts'] });
            router.reload({ only: ['monthlyRentals'] });
        },
    },
);

// Filter + sort are client-side · the heatmap loads the whole list eagerly
// and plate/brand/model are already in the payload, so it is instant (no
// round-trip, no skeleton). ?search= / ?direction= are mirrored to the URL
// without reloading.
const {
    search,
    direction: sortDirection,
    displayedVehicles,
    toggleSort,
} = usePlanningTableView(
    computed(() => props.vehicles),
    { initialDirection: props.sortDirection },
);

const exportOpen = ref<boolean>(false);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({
        value: year,
        label: String(year),
    })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

// True when the selected year has coded fiscal rules. `fiscalSupportedYears`
// is stable (preserved across the year-change partial reload), so support is
// derived client-side without an extra round-trip.
const fiscalSupported = computed<boolean>(() =>
    props.fiscalSupportedYears.includes(selectedYear.value),
);

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

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grow sm:max-w-md">
                    <SearchInput
                        v-model="search"
                        placeholder="Rechercher (immat, marque, modèle)"
                        aria-label="Rechercher un véhicule dans le planning"
                    />
                </div>
                <Button
                    variant="secondary"
                    :disabled="displayedVehicles.length === 0"
                    @click="exportOpen = true"
                >
                    <template #icon-left>
                        <Download :size="16" :stroke-width="1.75" />
                    </template>
                    Exporter
                </Button>
            </div>

            <p
                v-if="search.trim() !== '' && displayedVehicles.length === 0"
                class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
            >
                Aucun véhicule ne correspond à « {{ search }} ».
            </p>
            <Heatmap
                v-else
                :vehicles="displayedVehicles"
                :full-year-costs="localFullYearCosts"
                :real-costs="localRealCosts"
                :monthly-rentals="localMonthlyRentals"
                :fiscal-year="selectedYear"
                :fiscal-supported="fiscalSupported"
                :sort-direction="sortDirection"
                @cell-click="
                    (p) => week.open(p.vehicleId, p.week, selectedYear)
                "
                @sort-toggle="toggleSort"
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

        <PlanningExportModal
            v-model:open="exportOpen"
            :vehicles="displayedVehicles"
            :year="selectedYear"
        />
    </UserLayout>
</template>
