import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { store as storeRoute } from '@/routes/user/vehicles/controls/executions';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;

type RecordFormShape = {
    vehicle_id: number;
    control_definition_id: number | null;
    vehicle_control_override_id: number | null;
    executed_on: string;
    note: string;
    documents: File[];
};

/** Today as ISO `YYYY-MM-DD` (local), the sensible default execution date. */
function todayIso(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

/**
 * Form state for the "Fait" modal (Chantier B / B2): execution date (defaults
 * to today, retroactive allowed), optional note, 0..5 documents (multipart).
 * `seed()` targets the control the modal was opened on.
 */
export function useRecordExecutionForm(vehicleId: number): {
    form: InertiaForm<RecordFormShape>;
    fieldError: (key: string) => string | undefined;
    addDocuments: (files: FileList | null) => void;
    removeDocument: (index: number) => void;
    seed: (control: EffectiveControl) => void;
    submit: (onDone: () => void) => void;
} {
    const form = useForm<RecordFormShape>({
        vehicle_id: vehicleId,
        control_definition_id: null,
        vehicle_control_override_id: null,
        executed_on: todayIso(),
        note: '',
        documents: [],
    });

    function fieldError(key: string): string | undefined {
        return (form.errors as Record<string, string | undefined>)[key];
    }

    function addDocuments(files: FileList | null): void {
        if (files === null) {
            return;
        }

        form.documents = [...form.documents, ...Array.from(files)].slice(0, 5);
    }

    function removeDocument(index: number): void {
        form.documents.splice(index, 1);
    }

    function seed(control: EffectiveControl): void {
        form.clearErrors();
        form.vehicle_id = vehicleId;
        form.control_definition_id = control.isVehicleSpecific ? null : control.definitionId;
        form.vehicle_control_override_id = control.isVehicleSpecific ? control.overrideId : null;
        form.executed_on = todayIso();
        form.note = '';
        form.documents = [];
    }

    function submit(onDone: () => void): void {
        form
            .transform((data) => ({
                ...data,
                note: data.note.trim() === '' ? null : data.note.trim(),
            }))
            .post(storeRoute.url(vehicleId), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onDone(),
            });
    }

    return { form, fieldError, addDocuments, removeDocument, seed, submit };
}
