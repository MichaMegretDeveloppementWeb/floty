<script setup lang="ts">
/**
 * Company-focused planning view. Cell color = global density, cell
 * number = days used by the selected company, yearly total =
 * tax due by that company on the vehicle.
 */
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CompanyOptionTag from '@/Components/Domain/Company/CompanyOptionTag.vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import type {
    HeatmapFullYearCosts,
    HeatmapMonthlyRentals,
    HeatmapRealCosts,
} from '@/Components/Features/Planning/Heatmap/types';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { useUserPlanningIndex } from '@/Composables/Planning/Index/useUserPlanningIndex';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import { index as planningCompaniesIndexRoute } from '@/routes/user/planning/companies';

const props = defineProps<{
    vehicles: App.Data.User.Planning.PlanningHeatmapCompanyVehicleData[];
    company: App.Data.User.Company.CompanyOptionData;
    companies: App.Data.User.Company.CompanyOptionData[];
    /** Inertia::defer "fast" (cached fiscal full-year). */
    fullYearCosts?: HeatmapFullYearCosts;
    /** Inertia::defer "slow" (real billed tax, uncached). */
    realCosts?: HeatmapRealCosts;
    /** Monthly rentals for this company; null per month when pricing missing. */
    monthlyRentals?: HeatmapMonthlyRentals;
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
}>();

const localFullYearCosts = ref<HeatmapFullYearCosts | undefined>(props.fullYearCosts);
const localRealCosts = ref<HeatmapRealCosts | undefined>(props.realCosts);
const localMonthlyRentals = ref<HeatmapMonthlyRentals | undefined>(props.monthlyRentals);

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
    ['vehicles', 'company', 'companies', 'selectedYear'],
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

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const companyById = computed(() => {
    const map = new Map<number, App.Data.User.Company.CompanyOptionData>();

    for (const c of props.companies) {
map.set(c.id, c);
}

    return map;
});

const companyIdModel = computed<number | null>({
    get: () => props.company.id,
    set: (v: string | number | null) => {
        if (typeof v !== 'number' || v === props.company.id) {
return;
}

        // Full-page visit (scope changes) preserving current ?year=.
        const target = new URL(
            planningCompaniesIndexRoute.url({ company: v }),
            window.location.origin,
        );
        const currentYear = new URL(window.location.href).searchParams.get('year');

        if (currentYear !== null) {
            target.searchParams.set('year', currentYear);
        }

        router.visit(target.pathname + target.search, {
            preserveState: false,
            preserveScroll: false,
        });
    },
});

const { week, onContractsCreated } = useUserPlanningIndex();
</script>

<template>
    <Head :title="`Vue par entreprise · ${company.shortCode}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <div>
                    <p class="eyebrow mb-1">Planning</p>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                    >
                        Vue par entreprise
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Heatmap focalisée sur une entreprise. Le chiffre dans
                        chaque cellule correspond aux jours utilisés par
                        l'entreprise sélectionnée. La couleur reste pilotée
                        par l'occupation globale du véhicule.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <div class="min-w-[260px] max-w-md">
                        <SearchableSelect
                            id="company-planning-picker"
                            v-model="companyIdModel"
                            placeholder="Choisir une entreprise…"
                            :options="companyOptions"
                        >
                            <template #option="{ option }">
                                <CompanyOptionTag
                                    v-if="companyById.get(Number(option.value))"
                                    :company="companyById.get(Number(option.value))!"
                                />
                                <template v-else>{{ option.label }}</template>
                            </template>
                            <template #selected="{ option }">
                                <CompanyOptionTag
                                    v-if="companyById.get(Number(option.value))"
                                    :company="companyById.get(Number(option.value))!"
                                />
                                <template v-else>{{ option.label }}</template>
                            </template>
                        </SearchableSelect>
                    </div>
                    <InlineYearSelector
                        id="company-planning-year"
                        v-model="yearModel"
                        :options="yearOptions"
                    />
                </div>
            </header>

            <Heatmap
                :vehicles="vehicles"
                :full-year-costs="localFullYearCosts"
                :real-costs="localRealCosts"
                :monthly-rentals="localMonthlyRentals"
                :fiscal-year="selectedYear"
                @cell-click="(p) => week.open(p.vehicleId, p.week, selectedYear, company.id)"
            />
        </div>

        <WeekDrawer
            :open="week.drawerOpen.value"
            :week="week.weekData.value"
            :companies="companies"
            :fiscal-year="selectedYear"
            :locked-company="company"
            @close="week.close"
            @contracts-created="onContractsCreated"
        />
    </UserLayout>
</template>
