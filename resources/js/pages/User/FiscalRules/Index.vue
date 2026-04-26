<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import Tabs from '@/Components/Ui/Tabs/Tabs.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import {
    useOfficialLegalLinks,
    type LegalReference as LegalRef,
} from '@/Composables/Shared/useOfficialLegalLinks';
import {
    cadreSectionsOrder,
    calculSectionsOrder,
    fiscalRulesContent2024,
    sectionTitles,
    type RuleSection,
    type RuleTab,
} from '@/data/fiscalRulesContent';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type LegalReference = {
    type: string;
    article?: string;
    reference?: string;
    paragraph?: string;
};

type Rule = {
    id: number;
    ruleCode: string;
    name: string;
    description: string;
    ruleType: string;
    taxesConcerned: string[];
    legalBasis: LegalReference[];
    isActive: boolean;
};

const props = defineProps<{
    rules: Rule[];
}>();

const { currentYear: fiscalYear, daysInYear: daysInFiscalYear } =
    useFiscalYear();

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
    section: RuleSection;
    title: string;
    subtitle: string;
    codes: string[];
};

const currentGroups = computed((): SectionGroup[] => {
    const sections =
        activeTab.value === 'calcul'
            ? calculSectionsOrder
            : cadreSectionsOrder;

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

const taxLabel: Record<string, string> = {
    co2: 'CO₂',
    pollutants: 'Polluants',
};

const { resolveAll: resolveLegalLinks } = useOfficialLegalLinks();

const legalLinksFor = (refs: LegalReference[]) =>
    resolveLegalLinks(refs as LegalRef[]);

const taxBadgeTone = (
    taxes: string[],
): 'slate' | 'blue' | 'emerald' | 'amber' | 'rose' => {
    if (taxes.includes('co2') && taxes.includes('pollutants')) return 'blue';
    if (taxes.includes('co2')) return 'blue';
    if (taxes.includes('pollutants')) return 'amber';
    return 'slate';
};
</script>

<template>
    <Head title="Règles de calcul" />

    <UserLayout>
        <div class="flex flex-col gap-12">
            <header>
                <p class="eyebrow mb-1">Fiscalité</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Règles de calcul · {{ fiscalYear }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Comment l'application calcule les taxes CO₂ et polluants
                    pour chaque attribution de véhicule. Lisez l'onglet
                    « Calcul des taxes » pour reproduire un montant à la main.
                </p>
            </header>

            <!-- Formule générale -->
            <section
                class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5"
            >
                <p class="eyebrow mb-2">Formule générale</p>
                <p class="font-mono text-sm leading-relaxed text-slate-800">
                    <span class="text-slate-900 font-medium">Taxe due</span>
                    =
                    <span class="text-blue-700">(Tarif CO₂ + Tarif polluants)</span>
                    ×
                    <span class="text-slate-700">(jours utilisés / {{ daysInFiscalYear }})</span>
                    ×
                    <span class="text-emerald-700">(1 − exonérations)</span>
                </p>
                <p class="mt-3 text-sm text-slate-600">
                    Le tarif annuel plein dépend des caractéristiques du
                    véhicule ; le prorata dépend de l'attribution ; les
                    exonérations dépendent du couple véhicule × entreprise.
                </p>
            </section>

            <Tabs v-model="activeTab" :tabs="tabs" aria-label="Vue des règles" />

            <!-- Contenu onglet -->
            <div class="flex flex-col gap-12">
                <section
                    v-for="group in currentGroups"
                    :key="group.section"
                    class="flex flex-col gap-12"
                >
                    <header>
                        <h2
                            class="text-lg font-semibold tracking-tight text-slate-900"
                        >
                            {{ group.title }}
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-600">
                            {{ group.subtitle }}
                        </p>
                    </header>

                    <ul class="flex flex-col gap-12">
                        <li
                            v-for="code in group.codes"
                            :key="code"
                            class="rounded-xl border border-slate-200 bg-white p-5 transition-shadow duration-[120ms] ease-out hover:shadow-sm"
                            :class="
                                rulesByCode[code] && !rulesByCode[code].isActive
                                    ? 'opacity-70'
                                    : ''
                            "
                        >
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span
                                    class="font-mono text-xs font-semibold text-slate-500"
                                >
                                    {{ code }}
                                </span>
                                <Badge
                                    v-if="rulesByCode[code]?.taxesConcerned.length"
                                    :tone="
                                        taxBadgeTone(
                                            rulesByCode[code].taxesConcerned,
                                        )
                                    "
                                >
                                    <template
                                        v-for="(tax, i) in rulesByCode[
                                            code
                                        ].taxesConcerned"
                                        :key="tax"
                                    >
                                        {{ taxLabel[tax] ?? tax
                                        }}<span
                                            v-if="
                                                i <
                                                rulesByCode[code].taxesConcerned
                                                    .length -
                                                    1
                                            "
                                            >·</span
                                        >
                                    </template>
                                </Badge>
                                <StatusPill
                                    v-if="
                                        rulesByCode[code] &&
                                        !rulesByCode[code].isActive
                                    "
                                    tone="slate"
                                >
                                    Non applicable dans l'application
                                </StatusPill>
                            </div>

                            <h3
                                class="text-base font-semibold text-slate-900"
                            >
                                {{ fiscalRulesContent2024[code]?.title }}
                            </h3>
                            <p class="mt-1 text-base leading-relaxed text-slate-700">
                                {{ fiscalRulesContent2024[code]?.pitch }}
                            </p>

                            <!-- Condition / Effet (exonérations & aiguillage) -->
                            <div
                                v-if="
                                    fiscalRulesContent2024[code]?.appliesWhen ||
                                    fiscalRulesContent2024[code]?.effect
                                "
                                class="mt-3 flex flex-col gap-2 rounded-lg bg-slate-50 p-3 text-base"
                            >
                                <div
                                    v-if="fiscalRulesContent2024[code]?.appliesWhen"
                                    class="flex gap-2"
                                >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-500 shrink-0 pt-0.5 w-16"
                                        >Si</span
                                    >
                                    <span class="text-slate-700">{{
                                        fiscalRulesContent2024[code]!.appliesWhen
                                    }}</span>
                                </div>
                                <div
                                    v-if="fiscalRulesContent2024[code]?.effect"
                                    class="flex gap-2"
                                >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-500 shrink-0 pt-0.5 w-16"
                                        >Alors</span
                                    >
                                    <span class="text-slate-700">{{
                                        fiscalRulesContent2024[code]!.effect
                                    }}</span>
                                </div>
                            </div>

                            <!-- Body (explication longue) -->
                            <p
                                v-if="fiscalRulesContent2024[code]?.body"
                                class="mt-3 text-base leading-relaxed text-slate-600"
                            >
                                {{ fiscalRulesContent2024[code]!.body }}
                            </p>

                            <!-- Barème progressif -->
                            <div
                                v-if="
                                    fiscalRulesContent2024[code]
                                        ?.progressiveBrackets
                                "
                                class="mt-4"
                            >
                                <p class="eyebrow mb-2">
                                    Barème
                                    {{
                                        fiscalRulesContent2024[code]!
                                            .progressiveBrackets!.unit
                                    }}
                                </p>
                                <table
                                    class="w-full overflow-hidden rounded-lg border border-slate-200 text-base"
                                >
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th
                                                class="px-3 py-2 text-left font-medium text-slate-600"
                                            >
                                                {{
                                                    fiscalRulesContent2024[
                                                        code
                                                    ]!.progressiveBrackets!
                                                        .header[0]
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-right font-medium text-slate-600"
                                            >
                                                {{
                                                    fiscalRulesContent2024[
                                                        code
                                                    ]!.progressiveBrackets!
                                                        .header[1]
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in fiscalRulesContent2024[
                                                code
                                            ]!.progressiveBrackets!.rows"
                                            :key="row.label"
                                            class="border-t border-slate-100"
                                        >
                                            <td class="px-3 py-2 text-slate-700">
                                                {{ row.label }}
                                            </td>
                                            <td
                                                class="px-3 py-2 text-right font-mono text-slate-900"
                                            >
                                                {{ row.rate }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Barème forfaitaire -->
                            <div
                                v-if="fiscalRulesContent2024[code]?.flatBrackets"
                                class="mt-4"
                            >
                                <table
                                    class="w-full overflow-hidden rounded-lg border border-slate-200 text-base"
                                >
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th
                                                class="px-3 py-2 text-left font-medium text-slate-600"
                                            >
                                                {{
                                                    fiscalRulesContent2024[
                                                        code
                                                    ]!.flatBrackets!.header[0]
                                                }}
                                            </th>
                                            <th
                                                class="px-3 py-2 text-right font-medium text-slate-600"
                                            >
                                                {{
                                                    fiscalRulesContent2024[
                                                        code
                                                    ]!.flatBrackets!.header[1]
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in fiscalRulesContent2024[
                                                code
                                            ]!.flatBrackets!.rows"
                                            :key="row.category"
                                            class="border-t border-slate-100"
                                        >
                                            <td class="px-3 py-2">
                                                <span class="text-slate-700">{{
                                                    row.category
                                                }}</span>
                                                <span
                                                    v-if="row.note"
                                                    class="mt-0.5 block text-xs text-slate-500"
                                                    >{{ row.note }}</span
                                                >
                                            </td>
                                            <td
                                                class="px-3 py-2 text-right align-top font-mono font-medium text-slate-900"
                                            >
                                                {{ row.amount }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Exemple chiffré -->
                            <div
                                v-if="fiscalRulesContent2024[code]?.example"
                                class="mt-4 rounded-lg border border-blue-200 bg-blue-50/50 p-3"
                            >
                                <p
                                    class="eyebrow mb-1 text-blue-700"
                                >
                                    Exemple chiffré
                                </p>
                                <p class="text-base leading-relaxed text-slate-700">
                                    {{ fiscalRulesContent2024[code]!.example }}
                                </p>
                            </div>

                            <!-- Base légale — liens vers les textes officiels -->
                            <p
                                v-if="
                                    rulesByCode[code] &&
                                    rulesByCode[code].legalBasis.length > 0
                                "
                                class="mt-3 flex flex-wrap items-center gap-x-1 gap-y-0.5 font-mono text-xs text-slate-500"
                            >
                                <template
                                    v-for="(link, idx) in legalLinksFor(
                                        rulesByCode[code].legalBasis,
                                    )"
                                    :key="idx"
                                >
                                    <a
                                        v-if="link.url"
                                        :href="link.url"
                                        :title="link.title"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-slate-600 underline decoration-slate-300 underline-offset-2 transition-colors duration-[120ms] ease-out hover:text-slate-900 hover:decoration-slate-600"
                                    >
                                        {{ link.label }}
                                    </a>
                                    <span v-else>{{ link.label }}</span>
                                    <span
                                        v-if="
                                            idx <
                                            legalLinksFor(rulesByCode[code].legalBasis)
                                                .length -
                                                1
                                        "
                                        class="text-slate-300"
                                        aria-hidden="true"
                                    >
                                        ·
                                    </span>
                                </template>
                            </p>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </UserLayout>
</template>
