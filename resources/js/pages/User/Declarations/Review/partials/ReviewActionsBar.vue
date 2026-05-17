<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, FileCheck2, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
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

// Confirmation Mettre de côté · audit B21 pré-livraison.
// Évite la perte accidentelle de contexte de revue après plusieurs
// décisions tranchées (clic accidentel = retour à la liste).
const deferConfirmOpen = ref<boolean>(false);

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

    deferConfirmOpen.value = true;
}

function confirmMarkDeferred(): void {
    deferConfirmOpen.value = false;
    deferring.value = true;
    router.post(
        markDeferredRoute.url({ declaration: props.declarationId }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                deferring.value = false;
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

    <ConfirmModal
        v-model:open="deferConfirmOpen"
        title="Mettre la déclaration de côté ?"
        message="La déclaration passera en statut « différé ». Vous pourrez la reprendre à tout moment depuis cette même page. Aucune décision déjà prise ne sera perdue."
        confirm-label="Mettre de côté"
        cancel-label="Continuer la revue"
        @confirm="confirmMarkDeferred"
    />
</template>
