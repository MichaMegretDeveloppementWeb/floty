<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useFiscalRulesIndex } from '@/Composables/FiscalRule/Index/useFiscalRulesIndex';
import { useLocalYearSelector } from '@/Composables/Shared/useLocalYearSelector';
import { daysInYear as daysInYearOf } from '@/Utils/date/daysInYear';
import FormulaCard from './partials/FormulaCard.vue';
import RuleSection from './partials/RuleSection.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    rules: Rule[];
    selectedYear: number;
    /**
     * Scope d'années alimenté par le **registry des règles fiscales**
     * (chantier η Phase 5). Sémantique : « années pour lesquelles
     * l'application sait calculer des règles » (≠ scope contrats).
     */
    yearScope: App.Data.Shared.YearScopeData;
    /**
     * Phase 13 D5.12 · ADR-0022 v1.2 · organisation des tabs / sections
     * projetée depuis les enums PHP `RuleTab` + `RuleSection`. Plus
     * rien de hardcoded côté TS.
     */
    tabs: App.Data.User.Fiscal.FiscalRuleTabData[];
}>();

const { selectedYear, selectYear } = useLocalYearSelector(
    props.selectedYear,
    ['rules', 'selectedYear'],
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    props.yearScope.availableYears.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => selectedYear.value,
    set: (v) => selectYear(v),
});

const daysInFiscalYear = computed<number>(() => daysInYearOf(selectedYear.value));

const { activeTab, tabs, rulesByCode, currentGroups } = useFiscalRulesIndex(props);

const tabIntro = computed<string>(() => {
    switch (activeTab.value) {
        case 'calcul':
            return "Ce qui produit ou réduit un montant dans Floty · barèmes annuels pleins et exonérations actives.";
        case 'cadre':
            return "Comment l'application interprète le véhicule, le contrat et les évènements pour produire un calcul correct.";
        case 'hors-perimetre':
            return "Règles fiscales existantes en droit mais que Floty n'applique pas. Documentées pour transparence · pas de calcul, pas d'impact.";
        default:
            return '';
    }
});
</script>

<template>
    <Head title="Règles de calcul" />

    <UserLayout>
        <div class="flex flex-col gap-12">
            <!-- Header éditorial -->
            <header class="flex flex-wrap items-end justify-between gap-6">
                <div class="flex flex-col gap-2 max-w-3xl">
                    <p class="eyebrow">Fiscalité</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl">
                        Règles de calcul · {{ selectedYear }}
                    </h1>
                    <p class="text-base leading-relaxed text-slate-600">
                        Comment Floty calcule les taxes CO₂ et polluants pour
                        chaque attribution de véhicule. L'onglet
                        <span class="font-medium text-slate-900">Calcul des taxes</span>
                        détaille les barèmes et exonérations qui produisent le
                        montant final.
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

            <!-- Sous-onglets style underline border-bottom (mockup A) -->
            <nav
                class="flex gap-8 border-b border-slate-200"
                role="tablist"
                aria-label="Vue des règles"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.value"
                    :class="[
                        'cursor-pointer pb-3 -mb-px text-sm font-medium transition-colors duration-[120ms] ease-out border-b-2',
                        activeTab === tab.value
                            ? 'text-slate-900 border-slate-900'
                            : 'text-slate-500 border-transparent hover:text-slate-700',
                    ]"
                    @click="activeTab = tab.value"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <!-- Intro par onglet -->
            <p
                v-if="tabIntro"
                class="-mt-8 max-w-3xl text-sm leading-relaxed text-slate-500"
            >
                {{ tabIntro }}
            </p>

            <!-- Sections de l'onglet actif -->
            <div class="flex flex-col gap-16">
                <RuleSection
                    v-for="group in currentGroups"
                    :key="group.section"
                    :title="group.title"
                    :subtitle="group.subtitle"
                    :codes="group.codes"
                    :rules-by-code="rulesByCode"
                />
            </div>
        </div>
    </UserLayout>
</template>
