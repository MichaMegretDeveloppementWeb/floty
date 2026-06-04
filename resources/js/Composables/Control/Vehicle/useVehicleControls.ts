import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { status as statusRoute } from '@/routes/user/vehicles/controls';
import { destroy as destroyOverrideRoute } from '@/routes/user/vehicles/controls/overrides';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;
type VehicleControlStatus = App.Enums.Control.VehicleControlStatus;

/**
 * Interaction state for the vehicle "Contrôles" tab (Chantier B / B2): which
 * control the record / editor / history modals act on, the reset confirmation,
 * and the quick status toggle. Mutations redirect back to the tab, refreshing
 * the resolved controls. The editor and record forms live in their own
 * composables.
 */
export function useVehicleControls(vehicleId: number): {
    editorOpen: Ref<boolean>;
    editingControl: Ref<EffectiveControl | null>;
    recordOpen: Ref<boolean>;
    recordTarget: Ref<EffectiveControl | null>;
    historyOpen: Ref<boolean>;
    historyTarget: Ref<EffectiveControl | null>;
    confirmResetOpen: Ref<boolean>;
    resetTarget: Ref<EffectiveControl | null>;
    resetProcessing: Ref<boolean>;
    openCreate: () => void;
    openEditor: (control: EffectiveControl) => void;
    openRecord: (control: EffectiveControl) => void;
    openHistory: (control: EffectiveControl) => void;
    askReset: (control: EffectiveControl) => void;
    performReset: () => void;
    setStatus: (control: EffectiveControl, status: VehicleControlStatus) => void;
} {
    const editorOpen = ref<boolean>(false);
    const editingControl = ref<EffectiveControl | null>(null);
    const recordOpen = ref<boolean>(false);
    const recordTarget = ref<EffectiveControl | null>(null);
    const historyOpen = ref<boolean>(false);
    const historyTarget = ref<EffectiveControl | null>(null);
    const confirmResetOpen = ref<boolean>(false);
    const resetTarget = ref<EffectiveControl | null>(null);
    const resetProcessing = ref<boolean>(false);

    function openCreate(): void {
        editingControl.value = null;
        editorOpen.value = true;
    }

    function openEditor(control: EffectiveControl): void {
        editingControl.value = control;
        editorOpen.value = true;
    }

    function openRecord(control: EffectiveControl): void {
        recordTarget.value = control;
        recordOpen.value = true;
    }

    function openHistory(control: EffectiveControl): void {
        historyTarget.value = control;
        historyOpen.value = true;
    }

    function askReset(control: EffectiveControl): void {
        resetTarget.value = control;
        confirmResetOpen.value = true;
    }

    function performReset(): void {
        const control = resetTarget.value;

        if (control === null || control.overrideId === null) {
            return;
        }

        router.delete(destroyOverrideRoute.url({ vehicle: vehicleId, override: control.overrideId }), {
            preserveScroll: true,
            onStart: () => {
                resetProcessing.value = true;
            },
            onFinish: () => {
                resetProcessing.value = false;
            },
            onSuccess: () => {
                confirmResetOpen.value = false;
                resetTarget.value = null;
            },
        });
    }

    function setStatus(control: EffectiveControl, status: VehicleControlStatus): void {
        const payload = control.isVehicleSpecific
            ? { vehicle_control_override_id: control.overrideId, status }
            : { control_definition_id: control.definitionId, status };

        router.post(statusRoute.url(vehicleId), payload, {
            preserveScroll: true,
        });
    }

    return {
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
    };
}
