<script setup lang="ts">
/**
 * Domaine B · catalogue des contrôles réglementaires globaux (Chantier B / B1),
 * son propre domaine. Liste non paginée (petit ensemble borné), édition inline
 * via modale, suppression en soft-delete. Le contexte de rappel hérité
 * (paramètres généraux) est passé à l'éditeur. Logique en composables.
 */
import { Head } from '@inertiajs/vue3';
import { ClipboardCheck, Plus, Trash2 } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { useControlsCatalog } from '@/Composables/Control/Index/useControlsCatalog';
import { useControlLabels } from '@/Composables/Control/useControlLabels';
import ControlEditorModal from './partials/ControlEditorModal.vue';

const props = defineProps<{
    controls: App.Data.User.Control.ControlDefinitionData[];
    reminderSettings: App.Data.User.Control.ControlReminderSettingsData;
    anchorOptions: App.Data.User.Vehicle.EnumOptionData[];
    durationUnitOptions: App.Data.User.Vehicle.EnumOptionData[];
}>();

const { anchorLabel, echeanceSummary } = useControlLabels(
    () => props.anchorOptions,
    () => props.durationUnitOptions,
);

const {
    editorOpen,
    editingControl,
    openCreate,
    openEdit,
    confirmOpen,
    deletingControl,
    deleteProcessing,
    askDelete,
    performDelete,
} = useControlsCatalog();
</script>

<template>
    <Head title="Contrôles réglementaires" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[56em] flex-col gap-6">
            <header class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-2xl font-semibold text-slate-900">
                        Contrôles réglementaires
                    </h1>
                    <p class="text-sm text-slate-500">
                        Contrôles appliqués par défaut à toute la flotte (contrôle technique,
                        pollution, etc.). Chaque véhicule pourra ensuite ajuster les siens.
                    </p>
                </div>
                <Button class="shrink-0" @click="openCreate">
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter un contrôle
                </Button>
            </header>

            <EmptyState
                v-if="props.controls.length === 0"
                title="Aucun contrôle réglementaire"
                description="Définissez les contrôles à suivre pour toute la flotte. Ils serviront de base aux échéances et rappels de chaque véhicule."
            >
                <template #icon>
                    <ClipboardCheck :size="22" :stroke-width="1.75" />
                </template>
                <template #actions>
                    <Button @click="openCreate">
                        <template #icon-left>
                            <Plus :size="14" :stroke-width="1.75" />
                        </template>
                        Ajouter un contrôle
                    </Button>
                </template>
            </EmptyState>

            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="control in props.controls"
                    :key="control.id"
                    class="group relative flex cursor-pointer flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50/60"
                    @click="openEdit(control)"
                >
                    <div class="flex items-start justify-between gap-3 pr-9">
                        <div class="flex min-w-0 flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">
                                    {{ control.name }}
                                </h2>
                                <Badge v-if="!control.isActive" tone="slate" :uppercase="false">
                                    Inactif
                                </Badge>
                            </div>
                            <p class="text-sm text-slate-600">
                                {{ echeanceSummary(control) }}
                            </p>
                            <p class="text-xs text-slate-400">
                                À partir de : {{ anchorLabel(control.anchor) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge v-if="control.impliesUnavailability" tone="amber" :uppercase="false">
                            Indisponibilité
                        </Badge>
                        <Badge v-if="control.notifyDriver" tone="blue" :uppercase="false">
                            Prévient le conducteur
                        </Badge>
                        <Badge v-if="control.customizeReminders" tone="slate" :uppercase="false">
                            Rappels personnalisés
                        </Badge>
                        <span
                            v-if="control.ownRecipients.length > 0"
                            class="text-xs text-slate-400"
                        >
                            +{{ control.ownRecipients.length }}
                            destinataire{{ control.ownRecipients.length > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="absolute top-3 right-3 flex size-8 items-center justify-center rounded-lg text-slate-300 opacity-0 transition-all duration-[120ms] group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600 focus-visible:opacity-100"
                        aria-label="Supprimer ce contrôle"
                        @click.stop="askDelete(control)"
                    >
                        <Trash2 :size="16" :stroke-width="1.75" />
                    </button>
                </li>
            </ul>
        </div>

        <ControlEditorModal
            v-model:open="editorOpen"
            :control="editingControl"
            :reminder-settings="props.reminderSettings"
            :anchor-options="props.anchorOptions"
            :duration-unit-options="props.durationUnitOptions"
        />

        <ConfirmModal
            v-model:open="confirmOpen"
            tone="danger"
            title="Supprimer ce contrôle ?"
            :message="`Le contrôle « ${deletingControl?.name ?? ''} » sera supprimé. Cette action est réversible côté base, mais il ne sera plus appliqué.`"
            confirm-label="Supprimer"
            :loading="deleteProcessing"
            @confirm="performDelete"
        />
    </UserLayout>
</template>
