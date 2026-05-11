<script setup lang="ts">
/**
 * Ligne contrat dans le tableau breakdown d'une déclaration fiscale
 * (Phase 11 D5.8). Format compact, dense en information : période,
 * type LCD/LLD, label véhicule, résumé fiscal du véhicule (M1, WLTP,
 * Euro X), jours dans l'année, taxe totale (CO2 + polluants).
 *
 * Quand le contrat appartient à un cluster reconnu avec une décision
 * persistée reprise (`clusterDecisionRetainedFrom`), un petit badge
 * « Décision reprise » signale à l'utilisateur que la qualification
 * vient d'une version chaînée antérieure (amélioration B D5.8).
 *
 * Quand le contrat est opt-out (Requalified ou non-LCD), un badge
 * discret le marque pour rappeler que la taxe peut être 0 (motorisation
 * propre) ou >0 par requalification.
 */
import { History } from 'lucide-vue-next';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    contract: App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
}>();

const contractTypeLabel = computed<string>(() =>
    props.contract.contractType === 'lcd' ? 'LCD' : 'LLD',
);

const periodLabel = computed<string>(
    () => `${formatDateFr(props.contract.startDate)} → ${formatDateFr(props.contract.endDate)}`,
);

const isDecisionRetained = computed<boolean>(
    () => props.contract.clusterDecisionRetainedFrom !== null,
);
</script>

<template>
    <tr class="text-sm text-slate-700">
        <td class="px-3 py-2 align-top">
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
            <StatusPill :tone="contract.contractType === 'lcd' ? 'amber' : 'slate'">
                {{ contractTypeLabel }}
            </StatusPill>
        </td>
        <td class="px-3 py-2 align-top">
            <div class="flex flex-col gap-0.5">
                <span class="font-medium text-slate-900">{{ contract.vehicleLabel }}</span>
                <span class="text-[11px] text-slate-500">{{ contract.vehicleFiscalSummary }}</span>
            </div>
        </td>
        <td class="px-3 py-2 text-right align-top tabular-nums">
            {{ contract.daysInYearAssigned }}
        </td>
        <td class="px-3 py-2 text-right align-top">
            <div class="flex flex-col items-end gap-0.5">
                <span class="font-medium text-slate-900 tabular-nums">
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
