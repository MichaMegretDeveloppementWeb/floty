<script setup lang="ts">
/**
 * Header page Show déclaration (Phase 11 D5.6, enrichi D5.10.D pour
 * différenciateur Lecture/Revue, et D5.10.H pour le bouton Supprimer
 * brouillon en haut à droite responsive).
 *
 * Le bouton Supprimer apparaît UNIQUEMENT pour les statuts Draft et
 * Deferred (= brouillons non finalisés). Pour Generated (même
 * obsolète), aucun bouton de suppression · les déclarations émises
 * sont immuables (ADR-0008).
 */
import { Link, router } from '@inertiajs/vue3';
import { Building2, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as companyShowRoute } from '@/routes/user/companies';
import { destroy as destroyRoute } from '@/routes/user/declarations';
import { badgeForDeclaration } from '@/Utils/format/declarationStatus';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    /**
     * Référence du predecessor si ce brouillon le remplace. Permet de
     * personnaliser le message de confirmation de suppression.
     */
    predecessorReference?: string | null;
    /**
     * Vrai si la suppression réactivera le predecessor (cas
     * obsolescence purement volontaire). Faux sinon (predecessor
     * reste obsolète).
     */
    predecessorWillReactivate?: boolean;
}>();

const canDiscard = computed<boolean>(
    () => props.declaration.status === 'draft' || props.declaration.status === 'deferred',
);

const discarding = ref<boolean>(false);
const discardConfirmOpen = ref<boolean>(false);

const discardConfirmMessage = computed<string>(() => {
    const predRef = props.predecessorReference;
    if (predRef === null || predRef === undefined) {
        return 'Ce brouillon sera supprimé. Aucune autre déclaration n\'est concernée. Cette action est irréversible.';
    }

    if (props.predecessorWillReactivate) {
        return `Ce brouillon sera supprimé et la déclaration ${predRef} redeviendra active (la modification volontaire en cours sera annulée).`;
    }

    return `Ce brouillon sera supprimé. La déclaration ${predRef} restera obsolète et pourra être régénérée plus tard.`;
});

function requestDiscard(): void {
    if (discarding.value) {
        return;
    }
    discardConfirmOpen.value = true;
}

function confirmDiscard(): void {
    discardConfirmOpen.value = false;
    discarding.value = true;
    router.delete(destroyRoute.url({ declaration: props.declaration.id }), {
        preserveScroll: false,
        onFinish: () => {
            discarding.value = false;
        },
    });
}
</script>

<template>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <Building2 :size="22" :stroke-width="1.75" />
            </div>
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                        Déclaration fiscale
                    </p>
                    <StatusPill :tone="badgeForDeclaration(declaration.status, declaration.isObsolete).tone">
                        {{ badgeForDeclaration(declaration.status, declaration.isObsolete).label }}
                    </StatusPill>
                </div>
                <StatusPill tone="slate" class="w-fit">Lecture</StatusPill>
                <Link
                    :href="companyShowRoute.url({ company: declaration.companyId })"
                    class="group flex w-fit cursor-pointer flex-col gap-0.5 transition-colors duration-[120ms]"
                >
                    <h1 class="text-2xl font-semibold text-slate-900 group-hover:underline">
                        {{ declaration.companyShortCode }} · {{ declaration.fiscalYear }}
                    </h1>
                    <p class="text-sm text-slate-500 group-hover:text-slate-700">
                        {{ declaration.companyLegalName }}
                    </p>
                </Link>
                <p
                    class="mt-1 inline-flex w-fit items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-700"
                >
                    {{ declaration.internalLabel }}
                </p>
            </div>
        </div>

        <div v-if="canDiscard" class="flex flex-wrap items-center gap-2">
            <Button
                variant="destructive-soft"
                :disabled="discarding"
                @click="requestDiscard"
            >
                <LoaderCircle v-if="discarding" :size="16" :stroke-width="1.75" class="animate-spin" />
                <Trash2 v-else :size="16" :stroke-width="1.75" />
                Supprimer
            </Button>
        </div>
    </header>

    <ConfirmModal
        v-model:open="discardConfirmOpen"
        title="Supprimer le brouillon ?"
        :message="discardConfirmMessage"
        confirm-label="Supprimer"
        cancel-label="Annuler"
        tone="danger"
        @confirm="confirmDiscard"
    />
</template>
