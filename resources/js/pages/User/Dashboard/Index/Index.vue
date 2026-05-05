<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import KpisGrid from './partials/KpisGrid.vue';
import PageHeader from './partials/PageHeader.vue';
import QuickLinksGrid from './partials/QuickLinksGrid.vue';

const props = defineProps<{
    stats: App.Data.User.Dashboard.DashboardStatsData;
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 5). Remplace l'ancienne shared prop
     * `fiscal.availableYears` lue via `useFiscalYear`.
     */
    yearScope: App.Data.Shared.YearScopeData;
}>();

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['stats', 'selectedYear'],
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});
</script>

<template>
    <Head title="Tableau de bord" />

    <UserLayout>
        <div class="flex flex-col gap-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <PageHeader :fiscal-year="selectedYear" />
                <div class="flex flex-col gap-1">
                    <FieldLabel for="dashboard-year">Exercice</FieldLabel>
                    <SelectInput
                        id="dashboard-year"
                        v-model="yearModel"
                        :options="yearOptions"
                    />
                </div>
            </div>
            <KpisGrid :stats="props.stats" :fiscal-year="selectedYear" />
            <QuickLinksGrid />
        </div>
    </UserLayout>
</template>
