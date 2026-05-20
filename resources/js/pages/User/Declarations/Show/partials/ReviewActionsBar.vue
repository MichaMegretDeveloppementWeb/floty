<script setup lang="ts">
/**
 * Sticky bottom actions bar (mode B): Reporter opens a modal with an
 * optional reason (max 500 chars, cleared on revert/generation),
 * Générer triggers the final generation.
 */
import { router } from '@inertiajs/vue3';
import { Clock, FileCheck2, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import {
    generate as generateRoute,
    markDeferred as markDeferredRoute,
} from '@/routes/user/declarations';

const props = defineProps<{
    declarationId: number;
    pendingClustersCount: number;
    canGenerate: boolean;
    isDeferred: boolean;
}>();

const generating = ref<boolean>(false);
const deferring = ref<boolean>(false);
const isProcessing = computed<boolean>(() => generating.value || deferring.value);

const deferModalOpen = ref<boolean>(false);
const deferReason = ref<string>('');
const DEFER_REASON_MAX = 500;

const deferReasonRemaining = computed<number>(
    () => DEFER_REASON_MAX - deferReason.value.length,
);

const generateBlockedReason = computed<string | null>(() => {
    if (props.canGenerate) {
        return null;
    }

    if (props.pendingClustersCount > 0) {
        return `${props.pendingClustersCount} cluster${props.pendingClustersCount > 1 ? 's' : ''} en attente de décision avant génération.`;
    }

    return 'Conditions de génération non remplies.';
});

function requestMarkDeferred(): void {
    if (isProcessing.value || props.isDeferred) {
        return;
    }

    deferReason.value = '';
    deferModalOpen.value = true;
}

function confirmMarkDeferred(): void {
    if (deferring.value) {
        return;
    }

    const trimmed = deferReason.value.trim();

    deferring.value = true;
    router.post(
        markDeferredRoute.url({ declaration: props.declarationId }),
        { reason: trimmed === '' ? null : trimmed },
        {
            preserveScroll: true,
            onFinish: () => {
                deferring.value = false;
                deferModalOpen.value = false;
            },
        },
    );
}

function handleGenerate(): void {
    if (isProcessing.value) {
        return;
    }

    generating.value = true;
    router.post(
        generateRoute.url({ declaration: props.declarationId }),
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                generating.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur">
        <div class="flex flex-col">
            <p class="text-sm font-medium text-slate-900">
                {{ pendingClustersCount === 0
                    ? 'Toutes les décisions sont prises'
                    : `${pendingClustersCount} décision${pendingClustersCount > 1 ? 's' : ''} en attente`
                }}
            </p>
            <p class="text-xs text-slate-500">
                Vous pouvez sauvegarder l'état et revenir plus tard, ou générer
                une fois tous les clusters tranchés.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                variant="ghost"
                :disabled="isDeferred || isProcessing"
                @click="requestMarkDeferred"
            >
                <LoaderCircle v-if="deferring" :size="16" :stroke-width="1.75" class="animate-spin" />
                <Clock v-else :size="16" :stroke-width="1.75" />
                {{ isDeferred ? 'Reportée' : 'Reporter' }}
            </Button>

            <Tooltip v-if="!canGenerate" max-width="20rem">
                <Button :disabled="true">
                    <FileCheck2 :size="16" :stroke-width="1.75" />
                    Générer la déclaration
                </Button>
                <template #content>
                    {{ generateBlockedReason }}
                </template>
            </Tooltip>
            <Button v-else :disabled="isProcessing" @click="handleGenerate">
                <LoaderCircle v-if="generating" :size="16" :stroke-width="1.75" class="animate-spin" />
                <FileCheck2 v-else :size="16" :stroke-width="1.75" />
                {{ generating ? 'Génération en cours…' : 'Générer la déclaration' }}
            </Button>
        </div>
    </div>

    <Modal
        v-model:open="deferModalOpen"
        title="Reporter la déclaration ?"
        description="La déclaration passera en statut « Reportée ». Vous pourrez la reprendre à tout moment depuis cette même page."
        size="md"
    >
        <div class="flex flex-col gap-2">
            <label for="defer-reason" class="text-xs font-medium text-slate-700">
                Raison du report
                <span class="font-normal text-slate-500">· recommandée</span>
            </label>
            <textarea
                id="defer-reason"
                v-model="deferReason"
                data-autofocus
                rows="4"
                :maxlength="DEFER_REASON_MAX"
                placeholder="Ex. en attente du retour expert-comptable sur le cluster LCD du véhicule X…"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors duration-[120ms] focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100"
            />
            <p class="self-end font-mono text-[11px] tabular-nums text-slate-500">
                {{ deferReasonRemaining }} / {{ DEFER_REASON_MAX }}
            </p>
            <p class="text-[11px] text-slate-500">
                La raison sera affichée sur le brouillon tant qu'il reste reporté ·
                effacée automatiquement à la reprise ou à la génération.
            </p>
        </div>

        <template #footer>
            <Button variant="ghost" :disabled="deferring" @click="deferModalOpen = false">
                Continuer la revue
            </Button>
            <Button :disabled="deferring" :loading="deferring" @click="confirmMarkDeferred">
                <Clock v-if="!deferring" :size="16" :stroke-width="1.75" />
                Reporter
            </Button>
        </template>
    </Modal>
</template>
