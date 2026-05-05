<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import Tabs from '@/Components/Ui/Tabs/Tabs.vue';
import { useFiscalRulesIndex } from '@/Composables/FiscalRule/Index/useFiscalRulesIndex';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import { daysInYear as daysInYearOf } from '@/Utils/date/daysInYear';
import FormulaCard from './partials/FormulaCard.vue';
import RuleSection from './partials/RuleSection.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    rules: Rule[];
    selectedYear: number;
}>();

const { availableYears } = useFiscalYear();
const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['rules', 'selectedYear'],
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    availableYears.value.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

const daysInFiscalYear = computed<number>(() => daysInYearOf(selectedYear.value));

const { activeTab, tabs, rulesByCode, currentGroups } = useFiscalRulesIndex(props);
</script>

<template>
    <Head title="Règles de calcul" />

    <UserLayout>
        <div class="flex flex-col gap-12">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="eyebrow mb-1">Fiscalité</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                        Règles de calcul · {{ selectedYear }}
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Comment l'application calcule les taxes CO₂ et polluants
                        pour chaque attribution de véhicule. Lisez l'onglet
                        « Calcul des taxes » pour reproduire un montant à la main.
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <FieldLabel for="fiscal-rules-year">Exercice</FieldLabel>
                    <SelectInput
                        id="fiscal-rules-year"
                        v-model="yearModel"
                        :options="yearOptions"
                    />
                </div>
            </header>

            <FormulaCard :days-in-fiscal-year="daysInFiscalYear" />

            <Tabs v-model="activeTab" :tabs="tabs" aria-label="Vue des règles" />

            <div class="flex flex-col gap-12">
                <RuleSection
                    v-for="group in currentGroups"
                    :key="group.section"
                    :title="group.title"
                    :subtitle="group.subtitle"
                    :codes="group.codes"
                    :rules-by-code="rulesByCode"
                    :year="selectedYear"
                />
            </div>
        </div>
    </UserLayout>
</template>
