<script setup lang="ts">
/**
 * Carte de revue d'un cluster de risque fiscal (Phase 11 D4).
 *
 * Affiche : code de risque, niveau (badge), liste des contrats du
 * cluster (durée, intervalle), cumul jours dans l'année, fingerprint
 * (debug info masquée par défaut). Actions : Conserver / Requalifier
 * + champ justification.
 *
 * Si `decision` est déjà set (pré-application D3 par fingerprint
 * matching), on affiche un état de validation avec possibilité de
 * changer d'avis (re-submit).
 */
import { CheckCircle2, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { StatusTone } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    cluster: App.Data.User.FiscalDeclaration.ReviewClusterData;
    submitting: boolean;
}>();

const emit = defineEmits<{
    submit: [decision: 'conserved' | 'requalified', justification: string | null];
}>();

const justification = ref<string>(props.cluster.justification ?? '');

const isHighLevel = computed<boolean>(() => props.cluster.level === 'eleve');

const levelTone = computed<StatusTone>(() => (isHighLevel.value ? 'rose' : 'amber'));
const levelLabel = computed<string>(() => (isHighLevel.value ? 'Risque élevé' : 'Risque moyen'));

const codeLabel = computed<string>(() => {
    return props.cluster.code === 'R-LCD-CHAIN-FORT'
        ? 'Chaîne LCD forte'
        : 'Chaîne LCD';
});

const isResolved = computed<boolean>(() => props.cluster.decision !== null);

const justificationRequired = computed<boolean>(
    () => isHighLevel.value,
);

function handleConserve(): void {
    if (justificationRequired.value && justification.value.trim() === '') {
        return;
    }
    emit('submit', 'conserved', justification.value.trim() || null);
}

function handleRequalify(): void {
    emit('submit', 'requalified', justification.value.trim() || null);
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <component :is="isHighLevel ? ShieldAlert : ShieldCheck"
                            :size="18"
                            :stroke-width="1.75"
                            :class="isHighLevel ? 'text-rose-600' : 'text-amber-600'"
                        />
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ codeLabel }}
                        </h3>
                        <StatusPill :tone="levelTone">{{ levelLabel }}</StatusPill>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ cluster.contractsCount }} contrats LCD enchaînés ·
                        cumul {{ cluster.cumulativeDaysInYear }} jours sur l'année
                    </p>
                </div>
                <StatusPill v-if="isResolved && cluster.decision === 'conserved'" tone="emerald">
                    Conservée
                </StatusPill>
                <StatusPill v-else-if="isResolved && cluster.decision === 'requalified'" tone="rose">
                    Requalifiée
                </StatusPill>
                <StatusPill v-else tone="amber">À trancher</StatusPill>
            </div>
        </template>

        <div class="flex flex-col gap-4">
            <ul class="flex flex-col gap-1">
                <li
                    v-for="contract in cluster.contracts"
                    :key="contract.contractId"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600"
                >
                    <span class="font-mono text-slate-700">
                        {{ contract.vehiclePlate || `Véhicule ${contract.vehicleId}` }}
                    </span>
                    <span class="font-mono">
                        {{ formatDateFr(contract.startDate) }} → {{ formatDateFr(contract.endDate) }}
                    </span>
                    <span>· {{ contract.durationDaysInYear }} j</span>
                    <span v-if="contract.intervalBeforeDays !== null" class="text-slate-400">
                        ({{ contract.intervalBeforeDays }} j d'intervalle)
                    </span>
                </li>
            </ul>

            <div class="flex flex-col gap-2 border-t border-slate-100 pt-4">
                <TextInput
                    v-model="justification"
                    :label="justificationRequired ? 'Justification (obligatoire si conservée)' : 'Justification (optionnelle)'"
                    placeholder="Décrivez le contexte qui justifie votre choix..."
                    :hint="justificationRequired
                        ? 'Une justification est requise pour conserver l\'exonération sur un cluster de niveau élevé (ADR-0015 § 6.2).'
                        : undefined"
                />

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Button
                        variant="secondary"
                        :loading="submitting"
                        :disabled="justificationRequired && justification.trim() === ''"
                        @click="handleConserve"
                    >
                        <CheckCircle2 :size="16" :stroke-width="1.75" />
                        Conserver
                    </Button>
                    <Button variant="destructive-soft" :loading="submitting" @click="handleRequalify">
                        Requalifier en LLD
                    </Button>
                </div>
            </div>
        </div>
    </Card>
</template>
