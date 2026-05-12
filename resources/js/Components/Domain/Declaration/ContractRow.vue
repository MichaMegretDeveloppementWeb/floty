<script setup lang="ts">
/**
 * Ligne contrat dans le tableau breakdown d'une déclaration fiscale
 * (Phase 11 D5.8, enrichi D5.9.C avec `decisionIndicator`, refondu
 * D5.10.N avec badge cluster cliquable).
 *
 * Format compact, dense en information · période, type LCD/LLD,
 * label véhicule, résumé fiscal (M1, WLTP, Euro X), jours dans
 * l'année, taxe totale (CO2 + polluants).
 *
 * Indicateurs visuels ·
 *   - **Badge « Chaîne LCD »** (D5.10.N) · sous la pill LCD/LLD quand
 *     le contrat appartient à un cluster de risque. Clic ouvre la
 *     modale décisionnelle si `interactive`, sinon affichage lecture
 *     seule du détail.
 *   - **decisionIndicator** · matérialise le sort fiscal final du
 *     contrat dans la cellule TYPE. « LCD → LLD » (rose) quand le
 *     cluster a été requalifié, « LCD conservé » (emerald) quand
 *     conservé. Rien si pas de décision ou si contrat hors cluster.
 *   - **« Décision reprise »** · badge en cellule TAXE quand
 *     `clusterDecisionRetainedFrom` est posé (chaîne déclarative
 *     précédente, amélioration B D5.8).
 *
 * L'attribut `data-fingerprint` permet à `<DeclarationContractList>`
 * de faire défiler la page jusqu'à la 1ère row d'un cluster donné
 * (méthode exposée `scrollToCluster`).
 */
import { router } from '@inertiajs/vue3';
import { ArrowUpRight, CheckCircle2, History, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as showContractRoute } from '@/routes/user/contracts';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type ReviewCluster = App.Data.User.FiscalDeclaration.ReviewClusterData;

const props = withDefaults(
    defineProps<{
        contract: App.Data.User.FiscalDeclaration.ContractSnapshotEntryData;
        /**
         * Cluster auquel ce contrat appartient (résolu côté parent via
         * `clusterByFingerprint`). Null si hors cluster. Quand non
         * null, un badge cliquable « Chaîne LCD » est affiché dans la
         * cellule TYPE.
         */
        cluster?: ReviewCluster | null;
        /**
         * Active le clic sur le badge cluster (mode Review). Faux côté
         * page Show · le badge reste visible mais inopérant (lecture
         * seule de la décision persistée).
         */
        interactive?: boolean;
    }>(),
    {
        cluster: null,
        interactive: false,
    },
);

const emit = defineEmits<{
    /** Émis quand l'utilisateur clique sur le badge cluster. Le parent ouvre la modale décisionnelle. */
    'open-cluster': [cluster: ReviewCluster];
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

const hasCluster = computed<boolean>(() => props.cluster !== null);

const clusterBadgeClass = computed<string>(() => {
    const base = 'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-medium transition-colors duration-[120ms]';
    const tone = props.contract.clusterRiskLevel === 'eleve'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : 'border-amber-200 bg-amber-50 text-amber-700';
    const interactive = props.interactive ? 'cursor-pointer hover:opacity-80' : 'cursor-default';

    return `${base} ${tone} ${interactive}`;
});

interface DecisionIndicator {
    icon: typeof ArrowUpRight;
    label: string;
    toneClass: string;
    title: string;
}

function handleRowClick(): void {
    router.visit(showContractRoute.url({ contract: props.contract.contractId }));
}

function handleBadgeClick(event: MouseEvent): void {
    event.stopPropagation();
    if (props.interactive && props.cluster !== null) {
        emit('open-cluster', props.cluster);
    }
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
        class="cursor-pointer text-sm text-slate-700 transition-colors duration-[120ms] hover:bg-slate-50"
        :data-fingerprint="contract.clusterFingerprint ?? undefined"
        @click="handleRowClick"
    >
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
            <div class="flex flex-col items-start gap-1">
                <StatusPill :tone="contract.contractType === 'lcd' ? 'amber' : 'slate'">
                    {{ contractTypeLabel }}
                </StatusPill>
                <button
                    v-if="hasCluster"
                    type="button"
                    :class="clusterBadgeClass"
                    :title="interactive ? 'Chaîne LCD · cliquer pour décider' : 'Chaîne LCD · ce contrat appartient à une chaîne à risque'"
                    :disabled="!interactive"
                    @click="handleBadgeClick"
                >
                    <ShieldAlert :size="10" :stroke-width="1.75" />
                    Chaîne LCD
                </button>
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
        <td class="px-3 py-2 text-right align-top">
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
