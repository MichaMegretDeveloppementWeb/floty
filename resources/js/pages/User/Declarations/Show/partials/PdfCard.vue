<script setup lang="ts">
/**
 * PDF + adaptive CTA card on the declaration Show page. Renders only
 * for Generated states or non-head intermediate drafts (mode B uses
 * the interactive review directly).
 */
import { Link, router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Download,
    FileText,
    LoaderCircle,
    Pencil,
    Recycle,
    RefreshCcw,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import {
    download as downloadRoute,
    modify as modifyRoute,
    regenerate as regenerateRoute,
    show as showDeclarationRoute,
} from '@/routes/user/declarations';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = withDefaults(
    defineProps<{
        declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
        /** Downstream draft replacement; triggers "Reprendre la régénération". */
        successorDeclaration?: App.Data.User.FiscalDeclaration.DeclarationListItemData | null;
        /** Whether this declaration is the canonical head of (company, year). */
        isCanonicalHead?: boolean;
    }>(),
    {
        successorDeclaration: null,
        isCanonicalHead: true,
    },
);

const regenerating = ref<boolean>(false);
const modifying = ref<boolean>(false);
const modifyConfirmOpen = ref<boolean>(false);

const hasOngoingRegeneration = computed<boolean>(
    () => props.successorDeclaration !== null
        && props.successorDeclaration !== undefined
        && props.successorDeclaration.status === 'draft',
);

const canRegenerate = computed<boolean>(
    () => props.declaration.isObsolete
        && !hasOngoingRegeneration.value
        && props.isCanonicalHead,
);

const canModify = computed<boolean>(
    () => props.declaration.status === 'generated'
        && !props.declaration.isObsolete
        && !hasOngoingRegeneration.value
        && props.isCanonicalHead,
);

const isIntermediateDraft = computed<boolean>(
    () => !props.isCanonicalHead
        && (props.declaration.status === 'draft' || props.declaration.status === 'deferred'),
);

const copied = ref<boolean>(false);

function copyHash(): void {
    if (props.declaration.snapshotHash === null) {
        return;
    }

    void navigator.clipboard.writeText(props.declaration.snapshotHash);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

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
            <div class="flex flex-col gap-1.5">
                <p class="text-sm text-slate-600">
                    Document généré le
                    <span class="font-medium text-slate-900">{{ formatDateFr(declaration.generatedAt) }}</span>.
                </p>
                <div v-if="declaration.snapshotHash" class="flex flex-wrap items-start gap-2">
                    <p class="font-mono text-xs break-all text-slate-500">
                        SHA-256 · {{ declaration.snapshotHash }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-md border border-slate-200 px-1.5 py-0.5 text-xs text-slate-600 transition-colors duration-[120ms] hover:bg-slate-50"
                        :title="copied ? 'Copié !' : 'Copier l\'empreinte'"
                        @click="copyHash"
                    >
                        <Check
                            v-if="copied"
                            :size="12"
                            :stroke-width="1.75"
                            class="text-emerald-600"
                        />
                        <Copy v-else :size="12" :stroke-width="1.75" />
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button @click="handleDownload">
                    <Download :size="16" :stroke-width="1.75" />
                    Télécharger le PDF
                </Button>

                <Link
                    v-if="hasOngoingRegeneration && successorDeclaration"
                    :href="showDeclarationRoute.url({ declaration: successorDeclaration.id })"
                >
                    <Button variant="secondary">
                        <Recycle :size="16" :stroke-width="1.75" />
                        Poursuivre la régénération
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

        <div v-else-if="isIntermediateDraft" class="flex flex-col gap-3">
            <p class="text-sm text-slate-600">
                Ce brouillon est un intermédiaire orphelin de la chaîne ·
                un brouillon plus récent existe et porte la régénération
                en cours. Cette version ne peut plus être éditée ni
                générée. Vous pouvez la supprimer pour nettoyer
                l'historique.
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
