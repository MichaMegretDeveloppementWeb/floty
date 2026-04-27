<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Tabs from '@/Components/Ui/Tabs/Tabs.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import {
    cadreSectionsOrder,
    calculSectionsOrder,
    fiscalRulesContent2024,
    sectionTitles,
} from '@/data/fiscalRulesContent';
import type { RuleSection as RuleSectionKey, RuleTab } from '@/data/fiscalRulesContent';
import FormulaCard from './partials/FormulaCard.vue';
import RuleSection from './partials/RuleSection.vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    rules: Rule[];
}>();

const { currentYear: fiscalYear, daysInYear: daysInFiscalYear } = useFiscalYear();

const activeTab = ref<RuleTab>('calcul');

const tabs: Array<{ value: RuleTab; label: string; count?: number }> = [
    { value: 'calcul', label: 'Calcul des taxes' },
    { value: 'cadre', label: 'Cadre & fonctionnement' },
];

const rulesByCode = computed((): Record<string, Rule> => {
    const map: Record<string, Rule> = {};

    for (const r of props.rules) {
        map[r.ruleCode] = r;
    }

    return map;
});

type SectionGroup = {
    section: RuleSectionKey;
    title: string;
    subtitle: string;
    codes: string[];
};

const currentGroups = computed((): SectionGroup[] => {
    const sections =
        activeTab.value === 'calcul' ? calculSectionsOrder : cadreSectionsOrder;

    return sections.map((section) => {
        const codes = Object.entries(fiscalRulesContent2024)
            .filter(([, content]) => content.section === section)
            .map(([code]) => code);

        return {
            section,
            title: sectionTitles[section].title,
            subtitle: sectionTitles[section].subtitle,
            codes,
        };
    });
});
</script>

<template>
    <Head title="Règles de calcul" />

    <UserLayout>
        <div class="flex flex-col gap-12">
            <header>
                <p class="eyebrow mb-1">Fiscalité</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Règles de calcul · {{ fiscalYear }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Comment l'application calcule les taxes CO₂ et polluants
                    pour chaque attribution de véhicule. Lisez l'onglet « Calcul
                    des taxes » pour reproduire un montant à la main.
                </p>
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
                />
            </div>
        </div>
    </UserLayout>
</template>
