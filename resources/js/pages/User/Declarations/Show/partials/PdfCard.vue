<script setup lang="ts">
/**
 * Carte « PDF annexe documentaire » sur la page Show déclaration
 * (Phase 11 D5.4, enrichi D5.9.D avec CTA régénérer adaptatif).
 *
 * Actions :
 *   - **Télécharger le PDF** · toujours visible si le document a été
 *     généré.
 *   - **Reprendre la régénération en cours** · si la déclaration est
 *     déjà remplacée par un Draft chaîné (`successorDeclaration` avec
 *     statut `draft`). Lien direct vers la page Review du Draft.
 *     Évite de créer un brouillon orphelin supplémentaire.
 *   - **Régénérer la déclaration** · si la déclaration est obsolète
 *     mais qu'aucun Draft chaîné n'existe encore (S6 GeneratedObsolete
 *     Orphan). POST `/declarations/{id}/regenerate`.
 *   - **Aucun bouton régénérer** · si la déclaration est active non
 *     obsolète (S5) et ne sera pas régénérée sans changement de
 *     périmètre.
 */
import { Link, router } from '@inertiajs/vue3';
import { Download, FileText, LoaderCircle, Pencil, Recycle, RefreshCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    download as downloadRoute,
    modify as modifyRoute,
    regenerate as regenerateRoute,
    review as reviewRoute,
} from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    /**
     * Déclaration qui remplace la courante (chaîne aval). Si présent
     * et de statut `draft`, on est en cours de régénération · on
     * propose « Reprendre la régénération » au lieu de « Régénérer ».
     */
    successorDeclaration?: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
}>();

const regenerating = ref<boolean>(false);
const modifying = ref<boolean>(false);
const modifyConfirmOpen = ref<boolean>(false);

const hasOngoingRegeneration = computed<boolean>(
    () => props.successorDeclaration !== null
        && props.successorDeclaration !== undefined
        && props.successorDeclaration.status === 'draft',
);

const canRegenerate = computed<boolean>(
    () => props.declaration.isObsolete && !hasOngoingRegeneration.value,
);

/**
 * Phase 13 D5.10.E · le bouton « Modifier la déclaration » apparaît
 * uniquement quand la déclaration est S5 GeneratedActive (générée et
 * non obsolète) sans régénération en cours. Permet de déclencher
 * volontairement une transition S5 → S7 sans attendre une mutation
 * automatique de périmètre.
 */
const canModify = computed<boolean>(
    () => props.declaration.status === 'generated'
        && !props.declaration.isObsolete
        && !hasOngoingRegeneration.value,
);

const modifyConfirmMessage = computed<string>(() => {
    const ref = props.declaration.reference ?? `#${props.declaration.id}`;

    return `Cette action rendra la déclaration ${ref} obsolète et créera un nouveau brouillon pour modification. ${ref} restera consultable mais ne sera plus active. Vous pourrez annuler en supprimant le brouillon.`;
});

function handleRegenerate(): void {
    if (regenerating.value) {
        return;
    }

    regenerating.value = true;
    router.post(
        regenerateRoute.url({ declaration: props.declaration.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                regenerating.value = false;
            },
        },
    );
}

function handleDownload(): void {
    window.location.href = downloadRoute.url({ declaration: props.declaration.id });
}

function requestModify(): void {
    if (modifying.value) {
        return;
    }
    modifyConfirmOpen.value = true;
}

function confirmModify(): void {
    modifyConfirmOpen.value = false;
    modifying.value = true;
    router.post(
        modifyRoute.url({ declaration: props.declaration.id }),
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                modifying.value = false;
            },
        },
    );
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center gap-2">
                <FileText :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">PDF annexe documentaire</h2>
            </div>
        </template>

        <div v-if="declaration.generatedAt" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-sm text-slate-600">
                    Document généré le
                    <span class="font-medium text-slate-900">{{ formatDateFr(declaration.generatedAt) }}</span>.
                </p>
                <p v-if="declaration.generatedPdfHash" class="font-mono text-[11px] text-slate-400">
                    Hash · {{ declaration.generatedPdfHash.slice(0, 16) }}…
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button @click="handleDownload">
                    <Download :size="16" :stroke-width="1.75" />
                    Télécharger le PDF
                </Button>

                <Link
                    v-if="hasOngoingRegeneration && successorDeclaration"
                    :href="reviewRoute.url({ declaration: successorDeclaration.id })"
                >
                    <Button variant="secondary">
                        <Recycle :size="16" :stroke-width="1.75" />
                        Reprendre la régénération
                    </Button>
                </Link>

                <Button
                    v-else-if="canRegenerate"
                    variant="destructive-soft"
                    :disabled="regenerating"
                    @click="handleRegenerate"
                >
                    <LoaderCircle v-if="regenerating" :size="16" :stroke-width="1.75" class="animate-spin" />
                    <RefreshCcw v-else :size="16" :stroke-width="1.75" />
                    {{ regenerating ? 'Régénération…' : 'Régénérer' }}
                </Button>

                <Button
                    v-else-if="canModify"
                    variant="secondary"
                    :disabled="modifying"
                    @click="requestModify"
                >
                    <LoaderCircle v-if="modifying" :size="16" :stroke-width="1.75" class="animate-spin" />
                    <Pencil v-else :size="16" :stroke-width="1.75" />
                    {{ modifying ? 'Création du brouillon…' : 'Modifier la déclaration' }}
                </Button>
            </div>
        </div>

        <div v-else class="flex flex-col gap-2 text-sm text-slate-500">
            <p>
                Aucun PDF n'a encore été produit pour cette déclaration. Une
                fois la revue achevée, la génération produira un document
                immuable consultable et téléchargeable ici.
            </p>
        </div>

        <ConfirmModal
            v-model:open="modifyConfirmOpen"
            title="Modifier la déclaration ?"
            :message="modifyConfirmMessage"
            confirm-label="Créer le brouillon"
            cancel-label="Annuler"
            @confirm="confirmModify"
        />
    </Card>
</template>
