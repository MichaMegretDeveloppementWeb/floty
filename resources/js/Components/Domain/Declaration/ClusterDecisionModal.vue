<script setup lang="ts">
/**
 * Decision modal for an at-risk LCD cluster.
 * Opened from a ClusterGroup; lets the user Conserve / Requalify in LLD
 * with optional justification and a per-contract inclusion checklist.
 * Emits submit(decision, justification, excludedContractIds).
 */
import { ArrowUpRight, CheckCircle2, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    cluster: App.Data.User.FiscalDeclaration.ReviewClusterData | null;
    submitting: boolean;
    /** Configurable detection thresholds injected from FiscalRiskSettings. */
    riskSettings: App.Data.User.FiscalRiskSettings.FiscalRiskSettingsData;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    submit: [
        decision: 'conserved' | 'requalified',
        justification: string | null,
        excludedContractIds: number[],
    ];
}>();

const justification = ref<string>('');

/** Local per-contract inclusion state, initialised from cluster.excludedContractIds. */
const contractIncluded = ref<Record<number, boolean>>({});

watch(
    () => [open.value, props.cluster?.fingerprint],
    () => {
        if (open.value && props.cluster !== null) {
            justification.value = props.cluster.justification ?? '';
            const initial: Record<number, boolean> = {};
            const excluded = new Set(props.cluster.excludedContractIds ?? []);

            for (const contract of props.cluster.contracts) {
                initial[contract.contractId] = !excluded.has(contract.contractId);
            }

            contractIncluded.value = initial;
        }
    },
    { immediate: true },
);

const isHighRisk = computed<boolean>(() => props.cluster?.level === 'eleve');

const codeLabel = computed<string>(() => 'LCD successifs');

// Justification stays recommended (not required) for high-risk; user owns the arbitration.
const justificationRecommended = computed<boolean>(
    () => isHighRisk.value,
);

const includedCount = computed<number>(
    () => Object.values(contractIncluded.value).filter((v) => v).length,
);

/** A chain requires at least 2 contracts to remain valid. */
const canSubmit = computed<boolean>(() => includedCount.value >= 2);

const canConserve = computed<boolean>(() => canSubmit.value);

const canRequalify = computed<boolean>(() => canSubmit.value);

const effectiveVehiclesCount = computed<number>(() => {
    if (props.cluster === null) {
        return 0;
    }

    const includedVehicleIds = new Set<number>();

    for (const contract of props.cluster.contracts) {
        if (contractIncluded.value[contract.contractId]) {
            includedVehicleIds.add(contract.vehicleId);
        }
    }

    return includedVehicleIds.size;
});

const vehiclesLabel = computed<string>(() => {
    const count = effectiveVehiclesCount.value;

    return count > 1 ? `${count} véhicules` : '1 véhicule';
});

function computeExcludedIds(): number[] {
    if (props.cluster === null) {
        return [];
    }

    return props.cluster.contracts
        .filter((c) => !contractIncluded.value[c.contractId])
        .map((c) => c.contractId);
}

function handleClose(): void {
    open.value = false;
}

function handleConserve(): void {
    if (!canConserve.value || props.submitting) {
        return;
    }

    emit('submit', 'conserved', justification.value.trim() || null, computeExcludedIds());
}

function handleRequalify(): void {
    if (!canRequalify.value || props.submitting) {
        return;
    }

    emit('submit', 'requalified', justification.value.trim() || null, computeExcludedIds());
}
</script>

<template>
    <Modal
        v-model:open="open"
        :title="cluster ? `Décision · ${codeLabel}` : 'Décision'"
        size="md"
    >
        <div v-if="cluster" class="flex flex-col gap-5">
            <div class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <component
                        :is="isHighRisk ? ShieldAlert : ShieldCheck"
                        :size="18"
                        :stroke-width="1.75"
                        :class="isHighRisk ? 'text-rose-500' : 'text-amber-500'"
                    />
                    <span class="text-sm font-semibold text-slate-900">
                        {{ codeLabel }}
                    </span>
                    <StatusPill :tone="isHighRisk ? 'rose' : 'amber'">
                        {{ isHighRisk ? 'Risque élevé' : 'Risque moyen' }}
                    </StatusPill>
                </div>
                <p class="text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">{{ cluster.contractsCount }} contrats LCD</span>
                    couvrent une plage allant du
                    <span class="font-mono text-slate-700">{{ formatDateFr(cluster.coverageStartDate) }}</span>
                    au
                    <span class="font-mono text-slate-700">{{ formatDateFr(cluster.coverageEndDate) }}</span>
                    sur l'exercice
                    (<span class="font-semibold text-slate-900">{{ cluster.coveragePeriodDays }} jours</span>
                    couverts ·
                    <span class="text-slate-700">{{ vehiclesLabel }}</span>).
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        Contrats inclus dans la chaîne
                    </p>
                    <p class="text-[10px] text-slate-500">
                        {{ includedCount }} sur {{ cluster.contracts.length }} inclus
                    </p>
                </div>
                <p class="mb-2 text-[11px] text-slate-500">
                    Décochez un contrat pour le sortir de la chaîne ·
                    il sera traité comme un LCD individuel exempté
                    (R-2024-021).
                </p>
                <ul class="flex flex-col gap-1.5">
                    <li
                        v-for="contract in cluster.contracts"
                        :key="contract.contractId"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5"
                        :class="{ 'opacity-60': !contractIncluded[contract.contractId] }"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                :id="`include-${contract.contractId}`"
                                v-model="contractIncluded[contract.contractId]"
                                type="checkbox"
                                class="size-3.5 cursor-pointer rounded border-slate-300 text-slate-900 focus:ring-1 focus:ring-slate-400"
                            />
                            <label
                                :for="`include-${contract.contractId}`"
                                class="flex cursor-pointer flex-wrap items-center gap-2"
                            >
                                <span class="font-mono text-xs tabular-nums text-slate-700">
                                    {{ formatDateFr(contract.startDate) }} → {{ formatDateFr(contract.endDate) }}
                                </span>
                                <span class="font-mono text-[10px] text-slate-400">
                                    #{{ contract.contractId }} · {{ contract.vehiclePlate ?? `véh. ${contract.vehicleId}` }}
                                </span>
                            </label>
                        </div>
                        <span class="text-xs font-medium text-slate-900">
                            {{ contract.durationDaysInYear }} j
                        </span>
                    </li>
                </ul>
                <p
                    v-if="!canSubmit"
                    class="mt-2 text-[11px] font-medium text-rose-600"
                >
                    Au moins 2 contrats doivent être inclus pour former une chaîne.
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <p class="mb-1 font-semibold text-slate-900">Motif du signalement</p>
                <p>
                    Quand des contrats LCD courts forment une chaîne dont
                    l'union des jours couverts dépasse {{ riskSettings.thresholdLow }} jours
                    (chaîne moyenne) ou {{ riskSettings.thresholdHigh }} jours
                    / {{ riskSettings.countHigh }} contrats (chaîne forte) sur l'exercice,
                    l'administration peut requalifier ces locations courtes en location
                    longue durée (CIBS L. 421-141, BOFiP § 190), même si la chaîne
                    traverse plusieurs véhicules.
                </p>
                <ul class="mt-2 flex flex-col gap-1 pl-1">
                    <li class="flex items-baseline gap-1.5">
                        <span class="inline-block size-1.5 shrink-0 translate-y-[-1px] rounded-full bg-emerald-400" />
                        <span>
                            <strong class="text-slate-900">Conserver</strong> · garde
                            l'exonération LCD (R-2024-021) sur ces contrats.
                        </span>
                    </li>
                    <li class="flex items-baseline gap-1.5">
                        <span class="inline-block size-1.5 shrink-0 translate-y-[-1px] rounded-full bg-rose-400" />
                        <span>
                            <strong class="text-slate-900">Requalifier en LLD</strong> · applique la taxe
                            au prorata des jours d'usage de chaque contrat.
                        </span>
                    </li>
                </ul>
            </div>

            <div class="flex flex-col gap-1">
                <label for="cluster-justification" class="text-xs font-medium text-slate-700">
                    Justification
                    <span class="font-normal text-slate-500">
                        {{ justificationRecommended ? '· recommandée pour risque élevé' : '· recommandée' }}
                    </span>
                </label>
                <textarea
                    id="cluster-justification"
                    v-model="justification"
                    data-autofocus
                    rows="3"
                    maxlength="2000"
                    placeholder="Contexte métier, raison économique, motif particulier…"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors duration-[120ms] focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
                />
            </div>
        </div>

        <template #footer>
            <Button variant="ghost" :disabled="submitting" @click="handleClose">
                Annuler
            </Button>
            <Button
                variant="destructive-soft"
                :disabled="!canRequalify || submitting"
                :loading="submitting"
                @click="handleRequalify"
            >
                <ArrowUpRight :size="16" :stroke-width="1.75" />
                Requalifier en LLD
            </Button>
            <Button
                :disabled="!canConserve || submitting"
                :loading="submitting"
                @click="handleConserve"
            >
                <CheckCircle2 :size="16" :stroke-width="1.75" />
                Conserver
            </Button>
        </template>
    </Modal>
</template>
