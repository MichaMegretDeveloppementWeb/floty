<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useFullYearTaxBreakdownPanel } from '@/Composables/Vehicle/Show/useFullYearTaxBreakdownPanel';
import RuleCard from '@/pages/User/FiscalRules/Index/partials/RuleCard.vue';
import { formatEur } from '@/Utils/format/formatEur';
import {
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

const { breakdown, selectedCode, selectedRule, modalOpen, openRule } =
    useFullYearTaxBreakdownPanel(props);
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
                v-if="breakdown.appliedExemptions.length > 0"
                class="flex flex-col gap-2 border-t border-slate-100 pt-4"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Exonérations applicables
                </span>
                <ul class="flex flex-col gap-1.5 text-sm text-slate-700">
                    <li
                        v-for="exemption in breakdown.appliedExemptions"
                        :key="exemption.ruleCode"
                        class="flex items-start gap-2"
                    >
                        <span class="mt-0.5 shrink-0 text-emerald-600">✓</span>
                        <span class="flex-1">{{ exemption.reason }}</span>
                        <button
                            type="button"
                            class="shrink-0 cursor-pointer rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition-colors duration-[120ms] ease-out hover:bg-slate-200 hover:text-slate-900 focus-visible:bg-slate-200 focus-visible:outline-none"
                            :title="`Voir le détail de la règle ${exemption.ruleCode}`"
                            @click="openRule(exemption.ruleCode)"
                        >
                            {{ exemption.ruleCode }}
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Total final mis en valeur -->
            <section
                class="flex items-center justify-between gap-2 rounded-lg bg-transparent px-4 py-3 shadow-[0_0_3px_silver]"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-slate-700 uppercase"
                >
                    Total {{ props.stats.fiscalYear }}
                </span>
                <span class="font-mono text-lg font-semibold text-slate-700">
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
                :year="props.stats.fiscalYear"
            />
        </Modal>
    </Card>
</template>
