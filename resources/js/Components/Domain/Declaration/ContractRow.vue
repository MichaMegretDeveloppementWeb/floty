<script setup lang="ts">
/**
 * Contract row in a declaration breakdown table.
 * Shows period, LCD/LLD, vehicle, fiscal summary, days, CO2/pollutants and total.
 * decisionIndicator marks the final cluster outcome and "Decision reuse" badge
 * surfaces clusterDecisionRetainedFrom heritage.
 */
import { router } from '@inertiajs/vue3';
import { ArrowUpRight, CheckCircle2, History } from 'lucide-vue-next';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as showContractRoute } from '@/routes/user/contracts';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const props = withDefaults(
    defineProps<{
        contract: App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
        /** Background class for the tr, e.g. bg-slate-50 for clustered contracts. */
        bgClass?: string;
        /** Accent class (border-l-2 + color) for the first td when clustered. */
        accentBorderClass?: string;
    }>(),
    {
        bgClass: '',
        accentBorderClass: '',
    },
);

const contractTypeLabel = computed<string>(() =>
    props.contract.contractType === 'lcd' ? 'LCD' : 'LLD',
);

const periodLabel = computed<string>(
    () => `${formatDateFr(props.contract.startDate)} → ${formatDateFr(props.contract.endDate)}`,
);

const isDecisionRetained = computed<boolean>(
    () => props.contract.clusterDecisionRetainedFrom !== null,
);

interface DecisionIndicator {
    icon: typeof ArrowUpRight;
    label: string;
    toneClass: string;
    title: string;
}

function handleRowClick(): void {
    router.visit(showContractRoute.url({ contract: props.contract.contractId }));
}

const decisionIndicator = computed<DecisionIndicator | null>(() => {
    const contract = props.contract;

    if (contract.clusterDecision === 'requalified' && contract.contractType === 'lcd') {
        return {
            icon: ArrowUpRight,
            label: 'LCD → LLD',
            toneClass: 'text-rose-600',
            title: 'Cluster requalifié · ce contrat LCD est traité comme une LLD pour la taxe.',
        };
    }

    if (contract.clusterDecision === 'conserved') {
        return {
            icon: CheckCircle2,
            label: 'LCD conservé',
            toneClass: 'text-emerald-600',
            title: 'Cluster conservé · ce contrat LCD garde l\'exonération (R-2024-021).',
        };
    }

    return null;
});
</script>

<template>
    <tr
        :class="[
            'cursor-pointer text-sm text-slate-700 transition-colors duration-[120ms] hover:bg-slate-50',
            bgClass,
        ]"
        @click="handleRowClick"
    >
        <td
            class="px-3 py-2 align-top"
            :class="[
                bgClass === '' ? 'border-x border-x-transparent' : 'border-x border-slate-200',
                accentBorderClass,
            ]"
        >
            <div class="flex flex-col gap-0.5">
                <span class="font-mono text-xs tabular-nums text-slate-700">
                    {{ periodLabel }}
                </span>
                <span class="text-[11px] text-slate-400">
                    {{ contract.contractReference ?? `#${contract.contractId}` }}
                </span>
            </div>
        </td>
        <td class="px-3 py-2 align-top">
            <div class="flex flex-col items-start gap-1">
                <StatusPill :tone="contract.contractType === 'lcd' ? 'amber' : 'slate'">
                    {{ contractTypeLabel }}
                </StatusPill>
                <span
                    v-if="decisionIndicator"
                    :class="['inline-flex items-center gap-1 text-[10px] font-medium', decisionIndicator.toneClass]"
                    :title="decisionIndicator.title"
                >
                    <component :is="decisionIndicator.icon" :size="11" :stroke-width="1.75" />
                    {{ decisionIndicator.label }}
                </span>
            </div>
        </td>
        <td class="px-3 py-2 align-top">
            <div class="flex flex-col gap-0.5">
                <span class="font-medium text-slate-900">{{ contract.vehicleLabel }}</span>
                <span class="text-[11px] text-slate-500">{{ contract.vehicleFiscalSummary }}</span>
                <span
                    v-if="contract.exemptionReason"
                    class="mt-0.5 text-[11px] italic text-slate-500"
                    :title="contract.exemptionReason"
                >
                    {{ contract.exemptionReason }}
                </span>
            </div>
        </td>
        <td class="px-3 py-2 text-right align-top tabular-nums">
            {{ contract.daysInYearAssigned }}
        </td>
        <td class="px-3 py-2 text-right align-top tabular-nums text-slate-600">
            {{ formatEur(contract.co2Due, 2) }}
        </td>
        <td class="px-3 py-2 text-right align-top tabular-nums text-slate-600">
            {{ formatEur(contract.pollutantsDue, 2) }}
        </td>
        <td class="border-x border-slate-200 px-3 py-2 text-right align-top" :class="{ 'border-x-transparent': bgClass === '' }">
            <div class="flex flex-col items-end gap-0.5">
                <span class="font-medium tabular-nums text-slate-900">
                    {{ formatEur(contract.totalDue, 2) }}
                </span>
                <span
                    v-if="isDecisionRetained"
                    class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-500"
                    :title="`Décision héritée de la déclaration #${contract.clusterDecisionRetainedFrom}`"
                >
                    <History :size="11" :stroke-width="1.75" />
                    Décision reprise
                </span>
            </div>
        </td>
    </tr>
</template>
