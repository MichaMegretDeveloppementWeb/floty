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
    /**
     * Lot 5 D1 · seuils paramétrables de détection injectés depuis
     * `FiscalRiskSettings` (page Settings). Le texte pédagogique du
     * modal interpole `thresholdLow` / `thresholdHigh` / `countHigh`
     * au lieu de hardcoder « 30 / 90 / 5 ».
     */
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

/**
 * Phase 13 D5.10.S · état local d'inclusion par contrat. Initialisé
 * depuis `cluster.excludedContractIds` à l'ouverture · permet de
 * pré-cocher correctement quand l'utilisateur rouvre une décision déjà
 * persistée.
 */
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

// La distinction R-LCD-CHAIN vs R-LCD-CHAIN-FORT est déjà portée par
// le pill « Risque élevé » / « Risque moyen » juste à côté · pas besoin
// de la dédoubler dans le libellé du code.
const codeLabel = computed<string>(() => 'LCD successifs');

/**
 * La justification reste **recommandée** sur risque élevé (le texte
 * du label le signale) mais n'est plus une condition d'activation
 * du bouton Conserver · doctrine validée user · l'arbitrage final
 * appartient à l'utilisateur, l'UI ne doit pas le bloquer, juste
 * l'inciter à documenter sa décision.
 */
const justificationRecommended = computed<boolean>(
    () => isHighRisk.value,
);

const includedCount = computed<number>(
    () => Object.values(contractIncluded.value).filter((v) => v).length,
);

/**
 * Phase 13 D5.10.S · une chaîne nécessite ≥ 2 contrats pour exister.
 * Si l'utilisateur en décoche trop, la décision n'est plus applicable.
 */
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

            <!--
                Phase 13 D5.10.S · contrats du cluster avec case à
                cocher « Inclure dans la chaîne ». Décocher un contrat
                le sort du cluster · il est traité comme LCD individuel
                exempté R-2024-021 et ne participe pas à l'opt-out en
                cas de décision Requalified.
            -->
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

            <!-- Explication réglementaire -->
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

            <!-- Justification -->
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
