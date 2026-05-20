<script setup lang="ts">
/**
 * Header for the declaration Show page. Buttons depend on status:
 *   draft     · "Supprimer le brouillon"
 *   deferred  · "Annuler le report" + "Supprimer"
 *   generated · none (immutable per ADR-0008)
 */
import { Link, router } from '@inertiajs/vue3';
import { Building2, LoaderCircle, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as companyShowRoute } from '@/routes/user/companies';
import { destroy as destroyRoute, revertDefer as revertDeferRoute } from '@/routes/user/declarations';
import { badgeForDeclaration } from '@/Utils/format/declarationStatus';

const props = defineProps<{
    declaration: App.Data.User.FiscalDeclaration.FiscalDeclarationData;
    /** Predecessor reference to personalise the delete confirm message. */
    predecessorReference?: string | null;
    /** True when delete will reactivate the predecessor (purely voluntary obsolescence). */
    predecessorWillReactivate?: boolean;
}>();

const isDraft = computed<boolean>(() => props.declaration.status === 'draft');
const isDeferred = computed<boolean>(() => props.declaration.status === 'deferred');
const canDiscard = computed<boolean>(() => isDraft.value || isDeferred.value);

const reverting = ref<boolean>(false);

function requestRevert(): void {
    if (reverting.value) {
        return;
    }

    reverting.value = true;
    router.post(
        revertDeferRoute.url({ declaration: props.declaration.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                reverting.value = false;
            },
        },
    );
}

const discarding = ref<boolean>(false);
const discardConfirmOpen = ref<boolean>(false);

const discardConfirmTitle = computed<string>(
    () => (isDeferred.value
        ? 'Supprimer cette déclaration reportée ?'
        : 'Supprimer le brouillon ?'),
);

const discardConfirmMessage = computed<string>(() => {
    const predRef = props.predecessorReference;
    const subject = isDeferred.value ? 'Cette déclaration reportée' : 'Ce brouillon';

    if (predRef === null || predRef === undefined) {
        return `${subject} sera supprimé${isDeferred.value ? 'e' : ''}. Aucune autre déclaration n'est concernée. Cette action est irréversible.`;
    }

    if (props.predecessorWillReactivate) {
        return `${subject} sera supprimé${isDeferred.value ? 'e' : ''} et la déclaration ${predRef} redeviendra active (la modification volontaire en cours sera annulée).`;
    }

    return `${subject} sera supprimé${isDeferred.value ? 'e' : ''}. La déclaration ${predRef} restera obsolète et pourra être régénérée plus tard.`;
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
                <StatusPill v-if="!canDiscard" tone="slate" class="w-fit">Lecture</StatusPill>
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
                v-if="isDeferred"
                variant="secondary"
                :disabled="reverting || discarding"
                @click="requestRevert"
            >
                <LoaderCircle v-if="reverting" :size="16" :stroke-width="1.75" class="animate-spin" />
                <RotateCcw v-else :size="16" :stroke-width="1.75" />
                Annuler le report
            </Button>
            <Button
                variant="destructive-soft"
                :disabled="discarding || reverting"
                @click="requestDiscard"
            >
                <LoaderCircle v-if="discarding" :size="16" :stroke-width="1.75" class="animate-spin" />
                <Trash2 v-else :size="16" :stroke-width="1.75" />
                {{ isDeferred ? 'Supprimer' : 'Supprimer le brouillon' }}
            </Button>
        </div>
    </header>

    <ConfirmModal
        v-model:open="discardConfirmOpen"
        :title="discardConfirmTitle"
        :message="discardConfirmMessage"
        :confirm-label="isDeferred ? 'Supprimer' : 'Supprimer le brouillon'"
        cancel-label="Annuler"
        tone="danger"
        @confirm="confirmDiscard"
    />
</template>
