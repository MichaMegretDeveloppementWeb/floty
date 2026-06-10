<script setup lang="ts">
/**
 * Onglet « Contrôles » d'un véhicule (Chantier B / B2). Liste les contrôles
 * effectifs (catalogue global surchargé + contrôles spécifiques) avec leur
 * prochaine échéance et statut, et les actions : marquer « Fait », surcharger,
 * mettre en pause / réactiver / désactiver, ajouter un contrôle spécifique,
 * consulter l'historique. Toute la logique vit dans les composables.
 */
import { Ban, BellRing, Check, ClipboardCheck, History, Pencil, Plus } from 'lucide-vue-next';
import ActionsMenu from '@/Components/Ui/ActionsMenu/ActionsMenu.vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import EmptyState from '@/Components/Ui/EmptyState/EmptyState.vue';
import FlagIcon from '@/Components/Ui/FlagIcon.vue';
import SchedulerStaleBanner from '@/Components/Ui/SchedulerStaleBanner.vue';
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

const { echeanceSummary, anchorLabel, scheduleStatusLabel, scheduleStatusTone, scheduleStatusIcon } = useControlLabels(
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

    return control.isVehicleSpecific ? 'Supprimer ce contrôle' : 'Rétablir les valeurs par défaut';
}

function resetMessage(control: EffectiveControl | null): string {
    if (control === null) {
        return '';
    }

    return control.isVehicleSpecific
        ? `Le contrôle « ${control.name} » sera retiré de ce véhicule.`
        : `Les personnalisations de « ${control.name} » pour ce véhicule seront effacées : il reprendra les valeurs du contrôle global.`;
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <SchedulerStaleBanner />

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

        <div v-else class="flex flex-col gap-3" role="list">
            <Card
                v-for="control in props.tabData.controls"
                :key="(control.isVehicleSpecific ? 'ovr-' : 'def-') + (control.overrideId ?? control.definitionId)"
                role="listitem"
            >
                <template #header>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <h3 class="truncate text-[15px] font-semibold text-slate-900">{{ control.name }}</h3>
                            <Badge tone="slate" :uppercase="false">
                                {{ control.isVehicleSpecific ? 'Spécifique' : 'Global' }}
                            </Badge>
                            <span
                                v-if="!control.isVehicleSpecific && control.isOverridden"
                                class="text-xs text-slate-400"
                            >
                                modifié
                            </span>
                            <FlagIcon
                                v-if="control.impliesUnavailability"
                                :icon="Ban"
                                tone="amber"
                                label="Génère une indisponibilité"
                                :size="15"
                            />
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <Badge v-if="control.status === 'disabled'" tone="slate" :uppercase="false">Désactivé</Badge>
                            <Badge v-else-if="control.status === 'paused'" tone="slate" :uppercase="false">En pause</Badge>
                            <template v-else>
                                <Badge :tone="scheduleStatusTone(control.scheduleStatus)" :uppercase="false">
                                    <component
                                        :is="scheduleStatusIcon(control.scheduleStatus)"
                                        :size="12"
                                        :stroke-width="1.75"
                                        class="mr-1"
                                    />
                                    {{ scheduleStatusLabel(control.scheduleStatus) }}
                                </Badge>
                                <span class="font-mono text-xs text-slate-500">
                                    {{ formatDateFr(control.nextDueDate) }}
                                </span>
                            </template>
                        </div>
                    </div>
                </template>

                <div
                    class="flex flex-col gap-1.5"
                    :class="control.status === 'disabled' ? 'opacity-60' : ''"
                >
                    <p class="text-sm text-slate-700">{{ echeanceSummary(control) }}</p>
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs text-slate-500">
                        <span>Dès {{ anchorLabel(control.anchor) }}</span>
                        <span>
                            · Dernier :
                            <template v-if="control.lastExecutionDate">{{ formatDateFr(control.lastExecutionDate) }}</template>
                            <template v-else>jamais réalisé</template>
                        </span>
                        <Badge v-if="control.notifyDriver" tone="blue" :uppercase="false">
                            <BellRing :size="12" :stroke-width="1.75" class="mr-1" />
                            Prévient le conducteur
                        </Badge>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <Button v-if="control.status !== 'disabled'" size="sm" @click="openRecord(control)">
                            <template #icon-left>
                                <Check :size="14" :stroke-width="1.75" />
                            </template>
                            Fait
                        </Button>
                        <Button v-else size="sm" variant="secondary" @click="setStatus(control, 'active')">
                            Réactiver
                        </Button>

                        <ActionsMenu>
                            <button type="button" @click="openHistory(control)">
                                <History :size="15" :stroke-width="1.75" />
                                Historique
                            </button>
                            <button type="button" @click="openEditor(control)">
                                <Pencil :size="15" :stroke-width="1.75" />
                                Modifier
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
                                v-if="(control.isVehicleSpecific && control.overrideId !== null) || (!control.isVehicleSpecific && control.isOverridden)"
                                type="button"
                                class="danger"
                                @click="askReset(control)"
                            >
                                {{ resetLabel(control) }}
                            </button>
                        </ActionsMenu>
                    </div>
                </template>
            </Card>
        </div>

        <RecordExecutionModal
            v-model:open="recordOpen"
            :vehicle-id="props.vehicleId"
            :control="recordTarget"
            :nature-suggestions="props.tabData.natureSuggestions"
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
