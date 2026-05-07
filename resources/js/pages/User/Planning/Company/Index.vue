<script setup lang="ts">
/**
 * Vue Entreprise (chantier P2). Variante de la Vue d'ensemble focalisée
 * sur une entreprise donnée. Couleur cellule = densité globale (signal
 * de disponibilité du véhicule). Chiffre cellule = jours utilisés par
 * cette entreprise. Total annuel ligne = jours et taxe dus par cette
 * entreprise pour ce véhicule.
 *
 * Le drawer reste en comportement Vue d'ensemble pour P2 ; P3 le rendra
 * company-locked et anonymisera les contrats des autres entreprises.
 */
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import CompanyOptionTag from '@/Components/Domain/Company/CompanyOptionTag.vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
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
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
}>();

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['vehicles', 'company', 'companies', 'selectedYear'],
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

// ── Sélecteur entreprise ─────────────────────────────────────────────
const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const companyById = computed(() => {
    const map = new Map<number, App.Data.User.Company.CompanyOptionData>();
    for (const c of props.companies) map.set(c.id, c);
    return map;
});

const companyIdModel = computed<number | null>({
    get: () => props.company.id,
    set: (v: string | number | null) => {
        if (typeof v !== 'number' || v === props.company.id) return;
        // Navigation full page : la heatmap et les data se recalculent
        // côté backend (pas de partial reload car le scope change).
        // Préserve le `?year=` actuel pour ne pas reset la sélection
        // d'année au changement d'entreprise.
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
