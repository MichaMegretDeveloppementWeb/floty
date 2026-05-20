<script setup lang="ts">
/**
 * Per-VFC-segment breakdown of the full-year tax. With a single VFC on
 * the year there is one block (CO2 + pollutants + total); when the VFC
 * changes, one block per segment is rendered and their sum matches the
 * year total (ADR-0005 day-by-day computation).
 */
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import { useFullYearTaxBreakdownPanel } from '@/Composables/Vehicle/Show/useFullYearTaxBreakdownPanel';
import RuleCard from '@/pages/User/FiscalRules/Index/partials/RuleCard.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';
import {
    homologationMethodLabel,
    pollutantCategoryLabel,
} from '@/Utils/labels/vehicleEnumLabels';

type Segment = App.Data.User.Vehicle.VehicleFullYearTaxSegmentData;

const props = withDefaults(
    defineProps<{
        stats: App.Data.User.Vehicle.VehicleUsageStatsData;
        unwrapped?: boolean;
    }>(),
    { unwrapped: false },
);

const { breakdown, selectedCode, selectedRule, modalOpen, openRule } =
    useFullYearTaxBreakdownPanel(props);

function segmentPeriodLabel(seg: Segment): string {
    const from = formatDateFr(seg.effectiveFromInYear);
    const to = formatDateFr(seg.effectiveToInYear);

    return `Du ${from} au ${to} · ${seg.daysInSegment} j`;
}
</script>

<template>
    <component :is="unwrapped ? 'div' : Card">
        <template v-if="!unwrapped" #header>
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Détail de la Taxe pleine {{ props.stats.fiscalYear }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Calcul théorique pour 100 % d'utilisation
                    <template v-if="breakdown.taxSegments.length > 1">
                        · segmenté par version VFC, le total agrège tous les segments.
                    </template>
                </p>
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <div
                v-for="(segment, index) in breakdown.taxSegments"
                :key="index"
                :class="breakdown.taxSegments.length > 1 ? 'rounded-lg border border-slate-200 bg-slate-50 p-4' : ''"
            >
                <p
                    v-if="breakdown.taxSegments.length > 1"
                    class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                    {{ segmentPeriodLabel(segment) }}
                </p>

                <section class="flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span
                            class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            Taxe CO₂
                        </span>
                        <Badge tone="blue">
                            {{ homologationMethodLabel[segment.co2Method] }}
                        </Badge>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <p class="font-mono text-base font-semibold text-slate-900">
                            {{ formatEur(segment.co2Due) }}
                        </p>
                        <p
                            v-if="breakdown.taxSegments.length > 1 || segment.co2Due !== segment.co2FullYearTariff"
                            class="text-xs text-slate-500"
                        >
                            ({{ formatEur(segment.co2FullYearTariff) }} annuel × {{ segment.daysInSegment }}/{{ breakdown.daysInYear }} j)
                        </p>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-500">
                        {{ segment.co2Explanation }}
                    </p>
                </section>

                <section class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-4">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span
                            class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                        >
                            Taxe polluants
                        </span>
                        <Badge tone="amber">
                            {{ pollutantCategoryLabel[segment.pollutantCategory] }}
                        </Badge>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <p class="font-mono text-base font-semibold text-slate-900">
                            {{ formatEur(segment.pollutantsDue) }}
                        </p>
                        <p
                            v-if="breakdown.taxSegments.length > 1 || segment.pollutantsDue !== segment.pollutantsFullYearTariff"
                            class="text-xs text-slate-500"
                        >
                            ({{ formatEur(segment.pollutantsFullYearTariff) }} annuel × {{ segment.daysInSegment }}/{{ breakdown.daysInYear }} j)
                        </p>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-500">
                        {{ segment.pollutantsExplanation }}
                    </p>
                </section>
            </div>

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

            <section
                v-if="!unwrapped"
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
            v-if="!unwrapped && breakdown.appliedRuleCodes.length > 0"
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

        <div
            v-if="unwrapped && breakdown.appliedRuleCodes.length > 0"
            class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 text-xs"
        >
            <span class="text-slate-500">Règles appliquées :</span>
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
    </component>
</template>
