<script setup lang="ts">
/**
 * Visual wrapper around the consecutive contracts of an at-risk LCD cluster.
 * Renders the cluster header (level badges + coverage + decision state +
 * optional "Decide" button) plus child rows via default slot and a closing row.
 */
import { CheckCircle2, Pencil, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import type { StatusTone } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = withDefaults(
    defineProps<{
        riskCode: App.Enums.FiscalReviewDecision.RiskCode;
        riskLevel: App.Enums.FiscalReviewDecision.RiskLevel;
        contractsCount: number;
        /** Coverage in days (= max_end - min_start + 1, clamped to the year). */
        coveragePeriodDays: number;
        /** Effective start date clamped to the year (ISO Y-m-d). */
        coverageStartDate: string;
        /** Effective end date clamped to the year (ISO Y-m-d). */
        coverageEndDate: string;
        /** Number of distinct vehicles in the chain. */
        distinctVehiclesCount: number;
        /** Parent table column count, used for header/footer colspan. */
        colspan: number;
        /** Current decision for this cluster (null = pending). */
        decision: App.Enums.FiscalReviewDecision.ReviewDecisionType | null;
        /** Show the "Decide" button (Review mode). */
        interactive?: boolean;
        /** Persisted justification shown read-only under the header. */
        justification?: string | null;
        /** HTML id applied to the header tr so scrollToCluster() can target it. */
        clusterId?: string;
    }>(),
    {
        interactive: false,
        justification: null,
        clusterId: undefined,
    },
);

const emit = defineEmits<{
    'edit-decision': [];
}>();

const isHighLevel = computed<boolean>(() => props.riskLevel === 'eleve');

const codeLabel = computed<string>(() => 'LCD successifs');

const levelTone = computed<StatusTone>(() => (isHighLevel.value ? 'rose' : 'amber'));
const levelLabel = computed<string>(() => (isHighLevel.value ? 'Risque élevé' : 'Risque moyen'));

const decisionPill = computed<{ tone: StatusTone; label: string } | null>(() => {
    if (props.decision === 'conserved') {
        return { tone: 'emerald', label: 'LCD maintenue' };
    }

    if (props.decision === 'requalified') {
        return { tone: 'rose', label: 'Requalifiée LLD' };
    }

    return null;
});

const accentBorderClass = computed<string>(() =>
    isHighLevel.value ? 'border-l-2 border-l-rose-400' : 'border-l-2 border-l-amber-400',
);

const editButtonLabel = computed<string>(
    () => (props.decision === null ? 'Arbitrer' : 'Réviser l\'arbitrage'),
);

const vehiclesLabel = computed<string>(() =>
    props.distinctVehiclesCount > 1
        ? `${props.distinctVehiclesCount} véhicules`
        : '1 véhicule',
);
</script>

<template>
    <tr :id="clusterId" class="bg-slate-50">
        <td
            :colspan="props.colspan"
            :class="['border-x border-t border-slate-200 px-3 py-2.5', accentBorderClass]"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <component
                        :is="isHighLevel ? ShieldAlert : ShieldCheck"
                        :size="16"
                        :stroke-width="1.75"
                        :class="isHighLevel ? 'text-rose-500' : 'text-amber-500'"
                    />
                    <span class="text-sm font-semibold text-slate-900">
                        {{ codeLabel }}
                    </span>
                    <StatusPill :tone="levelTone">{{ levelLabel }}</StatusPill>
                    <span class="text-xs text-slate-500">
                        {{ contractsCount }} contrats du
                        {{ formatDateFr(coverageStartDate) }} au
                        {{ formatDateFr(coverageEndDate) }} ·
                        {{ coveragePeriodDays }} jours couverts ·
                        {{ vehiclesLabel }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <StatusPill v-if="decisionPill !== null" :tone="decisionPill.tone">
                        <CheckCircle2 :size="12" :stroke-width="1.75" />
                        {{ decisionPill.label }}
                    </StatusPill>
                    <StatusPill v-else tone="amber">À arbitrer</StatusPill>
                    <Button
                        v-if="interactive"
                        size="sm"
                        variant="secondary"
                        @click="emit('edit-decision')"
                    >
                        <Pencil :size="13" :stroke-width="1.75" />
                        {{ editButtonLabel }}
                    </Button>
                </div>
            </div>
            <p
                v-if="justification && !interactive"
                class="mt-2 border-t border-slate-200 pt-2 text-xs italic text-slate-600"
            >
                {{ justification }}
            </p>
        </td>
    </tr>

    <slot />

    <tr class="bg-slate-50">
        <td
            :colspan="props.colspan"
            :class="['border-x border-b border-slate-200 p-0', accentBorderClass]"
        >
            <div class="h-1" />
        </td>
    </tr>
</template>
