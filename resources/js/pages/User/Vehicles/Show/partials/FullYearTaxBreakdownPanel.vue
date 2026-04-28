<script setup lang="ts">
import { computed, ref } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import RuleCard from '@/pages/User/FiscalRules/Index/partials/RuleCard.vue';
import { formatEur } from '@/Utils/format/formatEur';
import {
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

const breakdown = computed(() => props.stats.fullYearTaxBreakdown);

const rulesByCode = computed<Record<string, Rule>>(() => {
    const map: Record<string, Rule> = {};

    for (const rule of breakdown.value.appliedRules) {
        map[rule.ruleCode] = rule;
    }

    return map;
});

const selectedCode = ref<string | null>(null);
const selectedRule = computed<Rule | null>(() => {
    if (selectedCode.value === null) {
        return null;
    }

    return rulesByCode.value[selectedCode.value] ?? null;
});

const modalOpen = computed<boolean>({
    get: () => selectedCode.value !== null,
    set: (value) => {
        if (!value) {
            selectedCode.value = null;
        }
    },
});

const openRule = (code: string): void => {
    selectedCode.value = code;
};
</script>

<template>
    <Card>
        <template #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Détail du Coût plein {{ props.stats.fiscalYear }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Calcul théorique pour 100 % d'utilisation
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <!-- Section CO₂ -->
            <section class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        Taxe CO₂
                    </span>
                    <Badge tone="blue">
                        {{ homologationMethodLabel[breakdown.co2Method] }}
                    </Badge>
                </div>
                <p class="font-mono text-base font-semibold text-slate-900">
                    {{ formatEur(breakdown.co2FullYearTariff) }}
                </p>
                <p class="text-xs leading-relaxed text-slate-500">
                    {{ breakdown.co2Explanation }}
                </p>
            </section>

            <!-- Section Polluants -->
            <section class="flex flex-col gap-2 border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                    >
                        Taxe polluants
                    </span>
                    <Badge tone="amber">
                        {{ pollutantCategoryLabel[breakdown.pollutantCategory] }}
                    </Badge>
                </div>
                <p class="font-mono text-base font-semibold text-slate-900">
                    {{ formatEur(breakdown.pollutantsFullYearTariff) }}
                </p>
                <p class="text-xs leading-relaxed text-slate-500">
                    {{ breakdown.pollutantsExplanation }}
                </p>
            </section>

            <!-- Exonérations / abattements (si présents) -->
            <section
                v-if="breakdown.exemptionReasons.length > 0"
                class="flex flex-col gap-2 border-t border-slate-100 pt-4"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Exonérations applicables
                </span>
                <ul class="flex flex-col gap-1 text-sm text-slate-700">
                    <li
                        v-for="reason in breakdown.exemptionReasons"
                        :key="reason"
                        class="flex items-start gap-2"
                    >
                        <span class="text-emerald-600">✓</span>
                        <span>{{ reason }}</span>
                    </li>
                </ul>
            </section>

            <!-- Total final mis en valeur -->
            <section
                class="flex items-center justify-between gap-2 rounded-lg bg-slate-900 px-4 py-3"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-300 uppercase"
                >
                    Total {{ props.stats.fiscalYear }}
                </span>
                <span class="font-mono text-lg font-semibold text-white">
                    {{ formatEur(breakdown.total) }}
                </span>
            </section>
        </div>

        <template
            v-if="breakdown.appliedRuleCodes.length > 0"
            #footer
        >
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-400">Règles appliquées :</span>
                <button
                    v-for="code in breakdown.appliedRuleCodes"
                    :key="code"
                    type="button"
                    class="cursor-pointer rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-200 hover:text-slate-900 focus-visible:bg-slate-200 focus-visible:outline-none"
                    :title="`Voir le détail de la règle ${code}`"
                    @click="openRule(code)"
                >
                    {{ code }}
                </button>
            </div>
        </template>

        <Modal
            v-model:open="modalOpen"
            :title="selectedRule?.name ?? selectedCode ?? 'Règle fiscale'"
            :description="`Code ${selectedCode}`"
            size="lg"
        >
            <RuleCard
                v-if="selectedCode"
                :code="selectedCode"
                :rule="selectedRule ?? undefined"
            />
        </Modal>
    </Card>
</template>
