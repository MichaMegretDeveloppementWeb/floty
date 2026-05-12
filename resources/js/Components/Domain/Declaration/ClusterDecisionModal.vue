<script setup lang="ts">
/**
 * Modale de décision pour un cluster LCD à risque (Phase 12 D5.9.C).
 *
 * Ouvre depuis le bouton « Décider » d'un `<ClusterGroup>` et permet
 * de trancher la chaîne en Conserver / Requalifier en LLD avec
 * justification. Apporte le contexte pédagogique qui ne tenait pas
 * dans le tableau Review :
 *   - Récapitulatif du cluster (badge niveau + contrats count + cumul).
 *   - Calendrier des contrats du cluster (timeline simple avec
 *     intervalles entre contrats consécutifs).
 *   - Rappel réglementaire du seuil R-LCD-CHAIN et de l'effet de
 *     chaque décision sur la taxe finale.
 *   - Champ justification (textarea), obligatoire pour conserver une
 *     chaîne de niveau élevé.
 *
 * Aucune logique métier directe · émet `submit(decision, justification)`
 * que `<DeclarationContractList>` ré-émet au parent (Review/Index)
 * pour invoquer `useReviewForm.submitDecision`.
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
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    submit: [decision: 'conserved' | 'requalified', justification: string | null];
}>();

const justification = ref<string>('');

watch(
    () => [open.value, props.cluster?.fingerprint],
    () => {
        if (open.value && props.cluster !== null) {
            justification.value = props.cluster.justification ?? '';
        }
    },
    { immediate: true },
);

const isHighRisk = computed<boolean>(() => props.cluster?.level === 'eleve');

const codeLabel = computed<string>(() =>
    props.cluster?.code === 'R-LCD-CHAIN-FORT' ? 'Chaîne LCD forte' : 'Chaîne LCD',
);

const justificationRequired = computed<boolean>(
    () => isHighRisk.value && true,
);

const canConserve = computed<boolean>(
    () => !justificationRequired.value || justification.value.trim().length > 0,
);

const vehiclesLabel = computed<string>(() => {
    const count = props.cluster?.distinctVehiclesCount ?? 0;

    return count > 1 ? `${count} véhicules` : '1 véhicule';
});

function handleClose(): void {
    open.value = false;
}

function handleConserve(): void {
    if (!canConserve.value || props.submitting) {
        return;
    }

    emit('submit', 'conserved', justification.value.trim() || null);
}

function handleRequalify(): void {
    if (props.submitting) {
        return;
    }

    emit('submit', 'requalified', justification.value.trim() || null);
}
</script>

<template>
    <Modal
        v-model:open="open"
        :title="cluster ? `Décision · ${codeLabel}` : 'Décision'"
        size="md"
    >
        <div v-if="cluster" class="flex flex-col gap-5">
            <!-- Récap pédagogique -->
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

            <!-- Détail tabulaire des contrats du cluster (multi-véhicules supporté) -->
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    Contrats du cluster
                </p>
                <ul class="flex flex-col gap-1.5">
                    <li
                        v-for="contract in cluster.contracts"
                        :key="contract.contractId"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs tabular-nums text-slate-700">
                                {{ formatDateFr(contract.startDate) }} → {{ formatDateFr(contract.endDate) }}
                            </span>
                            <span class="font-mono text-[10px] text-slate-400">
                                #{{ contract.contractId }} · {{ contract.vehiclePlate ?? `véh. ${contract.vehicleId}` }}
                            </span>
                        </div>
                        <span class="text-xs font-medium text-slate-900">
                            {{ contract.durationDaysInYear }} j
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Explication réglementaire -->
            <div class="rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">
                <p class="mb-1 font-semibold text-slate-900">Pourquoi cette chaîne est à risque</p>
                <p>
                    Quand des contrats LCD courts forment une chaîne dont la
                    plage couverte dépasse 30 jours (chaîne moyenne) ou 90 jours
                    / 5 contrats (chaîne forte) sur l'exercice, l'administration
                    peut requalifier ces locations courtes en location longue
                    durée (CIBS L. 421-141, BOFiP § 190), même si la chaîne
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

            <!-- Justification -->
            <div class="flex flex-col gap-1">
                <label for="cluster-justification" class="text-xs font-medium text-slate-700">
                    Justification
                    <span class="font-normal text-slate-500">
                        {{ justificationRequired ? '· obligatoire pour conserver en risque élevé' : '· recommandée' }}
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
                <p
                    v-if="justificationRequired && justification.trim().length === 0"
                    class="text-[11px] text-slate-500"
                >
                    Sans justification, seul « Requalifier » est disponible.
                </p>
            </div>
        </div>

        <template #footer>
            <Button variant="ghost" :disabled="submitting" @click="handleClose">
                Annuler
            </Button>
            <Button
                variant="destructive-soft"
                :disabled="submitting"
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
