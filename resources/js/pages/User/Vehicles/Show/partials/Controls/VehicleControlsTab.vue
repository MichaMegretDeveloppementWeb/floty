<script setup lang="ts">
/**
 * Onglet « Contrôles » d'un véhicule (Chantier B / B2). Liste les contrôles
 * effectifs (catalogue global surchargé + contrôles spécifiques) avec leur
 * prochaine échéance et statut, et les actions : marquer « Fait », surcharger,
 * mettre en pause / réactiver / désactiver, ajouter un contrôle spécifique,
 * consulter l'historique. Toute la logique vit dans les composables.
 */
import { Check, ClipboardCheck, History, Pencil, Plus } from 'lucide-vue-next';
import ActionsMenu from '@/Components/Ui/ActionsMenu/ActionsMenu.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import { useControlLabels } from '@/Composables/Control/useControlLabels';
import { useVehicleControls } from '@/Composables/Control/Vehicle/useVehicleControls';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import ExecutionHistoryModal from './ExecutionHistoryModal.vue';
import RecordExecutionModal from './RecordExecutionModal.vue';
import VehicleControlEditorModal from './VehicleControlEditorModal.vue';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;

const props = defineProps<{
    vehicleId: number;
    tabData: App.Data.User.Control.Vehicle.VehicleControlsTabData;
}>();

const { echeanceSummary, anchorLabel, scheduleStatusLabel, scheduleStatusTone } = useControlLabels(
    () => props.tabData.anchorOptions,
    () => props.tabData.durationUnitOptions,
);

const {
    editorOpen,
    editingControl,
    recordOpen,
    recordTarget,
    historyOpen,
    historyTarget,
    confirmResetOpen,
    resetTarget,
    resetProcessing,
    openCreate,
    openEditor,
    openRecord,
    openHistory,
    askReset,
    performReset,
    setStatus,
} = useVehicleControls(props.vehicleId);

function resetLabel(control: EffectiveControl | null): string {
    if (control === null) {
        return '';
    }

    return control.isVehicleSpecific ? 'Supprimer ce contrôle' : 'Rétablir le défaut global';
}

function resetMessage(control: EffectiveControl | null): string {
    if (control === null) {
        return '';
    }

    return control.isVehicleSpecific
        ? `Le contrôle « ${control.name} » sera retiré de ce véhicule.`
        : `Le contrôle « ${control.name} » reprendra les réglages globaux par défaut pour ce véhicule.`;
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold text-slate-900">Contrôles réglementaires</h2>
                <p class="text-sm text-slate-500">
                    Échéances et rappels des contrôles de ce véhicule. Les contrôles globaux s'appliquent
                    par défaut ; vous pouvez les surcharger, les mettre en pause ou en ajouter de spécifiques.
                </p>
            </div>
            <Button class="shrink-0" size="sm" @click="openCreate">
                <template #icon-left>
                    <Plus :size="14" :stroke-width="1.75" />
                </template>
                Contrôle spécifique
            </Button>
        </div>

        <EmptyState
            v-if="props.tabData.controls.length === 0"
            title="Aucun contrôle pour ce véhicule"
            description="Définissez des contrôles globaux dans le catalogue, ou ajoutez un contrôle spécifique à ce véhicule."
        >
            <template #icon>
                <ClipboardCheck :size="22" :stroke-width="1.75" />
            </template>
        </EmptyState>

        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="control in props.tabData.controls"
                :key="(control.isVehicleSpecific ? 'ovr-' : 'def-') + (control.overrideId ?? control.definitionId)"
                :class="[
                    'flex flex-col gap-3 rounded-xl border bg-white p-4 transition-colors duration-[120ms]',
                    control.status === 'disabled' ? 'border-slate-200 opacity-60' : 'border-slate-200',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-[15px] font-semibold text-slate-900">{{ control.name }}</h3>
                            <Badge v-if="control.isVehicleSpecific" tone="slate" :uppercase="false">Spécifique</Badge>
                            <Badge v-else-if="control.isOverridden" tone="slate" :uppercase="false">Surchargé</Badge>
                        </div>
                        <p class="text-xs text-slate-500">{{ echeanceSummary(control) }}</p>
                        <p class="text-xs text-slate-400">À partir de : {{ anchorLabel(control.anchor) }}</p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <Badge v-if="control.status === 'disabled'" tone="slate" :uppercase="false">Désactivé</Badge>
                        <Badge v-else-if="control.status === 'paused'" tone="slate" :uppercase="false">En pause</Badge>
                        <Badge v-else :tone="scheduleStatusTone(control.scheduleStatus)" :uppercase="false">
                            {{ scheduleStatusLabel(control.scheduleStatus) }}
                        </Badge>
                        <span v-if="control.status !== 'disabled'" class="font-mono text-xs text-slate-500">
                            échéance {{ formatDateFr(control.nextDueDate) }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge v-if="control.impliesUnavailability" tone="amber" :uppercase="false">Indisponibilité</Badge>
                    <Badge v-if="control.notifyDriver" tone="blue" :uppercase="false">Prévient le conducteur</Badge>
                    <span class="text-xs text-slate-400">
                        Dernier :
                        <template v-if="control.lastExecutionDate">{{ formatDateFr(control.lastExecutionDate) }}</template>
                        <template v-else>jamais réalisé</template>
                    </span>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <Button
                        v-if="control.status !== 'disabled'"
                        size="sm"
                        @click="openRecord(control)"
                    >
                        <template #icon-left>
                            <Check :size="14" :stroke-width="1.75" />
                        </template>
                        Fait
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="secondary"
                        @click="setStatus(control, 'active')"
                    >
                        Réactiver
                    </Button>

                    <ActionsMenu>
                        <button type="button" @click="openHistory(control)">
                            <History :size="15" :stroke-width="1.75" />
                            Historique
                        </button>
                        <button type="button" @click="openEditor(control)">
                            <Pencil :size="15" :stroke-width="1.75" />
                            {{ control.isVehicleSpecific ? 'Modifier' : 'Surcharger' }}
                        </button>
                        <button
                            v-if="control.status === 'active'"
                            type="button"
                            @click="setStatus(control, 'paused')"
                        >
                            Mettre en pause
                        </button>
                        <button
                            v-else-if="control.status === 'paused'"
                            type="button"
                            @click="setStatus(control, 'active')"
                        >
                            Réactiver
                        </button>
                        <button
                            v-if="!control.isVehicleSpecific && control.status !== 'disabled'"
                            type="button"
                            @click="setStatus(control, 'disabled')"
                        >
                            Désactiver pour ce véhicule
                        </button>
                        <button
                            v-if="control.overrideId !== null"
                            type="button"
                            class="danger"
                            @click="askReset(control)"
                        >
                            {{ resetLabel(control) }}
                        </button>
                    </ActionsMenu>
                </div>
            </li>
        </ul>

        <RecordExecutionModal
            v-model:open="recordOpen"
            :vehicle-id="props.vehicleId"
            :control="recordTarget"
        />

        <VehicleControlEditorModal
            v-model:open="editorOpen"
            :vehicle-id="props.vehicleId"
            :control="editingControl"
            :reminder-settings="props.tabData.reminderSettings"
            :anchor-options="props.tabData.anchorOptions"
            :duration-unit-options="props.tabData.durationUnitOptions"
        />

        <ExecutionHistoryModal
            v-model:open="historyOpen"
            :vehicle-id="props.vehicleId"
            :control="historyTarget"
        />

        <ConfirmModal
            v-model:open="confirmResetOpen"
            :tone="resetTarget?.isVehicleSpecific ? 'danger' : 'default'"
            :title="resetLabel(resetTarget)"
            :message="resetMessage(resetTarget)"
            :confirm-label="resetTarget?.isVehicleSpecific ? 'Supprimer' : 'Rétablir'"
            :loading="resetProcessing"
            @confirm="performReset"
        />
    </div>
</template>
