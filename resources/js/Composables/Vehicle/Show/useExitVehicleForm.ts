import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { exit as vehiclesExitRoute } from '@/routes/user/vehicles';
import { vehicleExitReasonLabel } from '@/Utils/labels/vehicleEnumLabels';

type VehicleExitReason = App.Enums.Vehicle.VehicleExitReason;

type FormShape = {
    exit_date: string;
    exit_reason: VehicleExitReason | '';
    note: string;
};

type SelectOption = { value: VehicleExitReason; label: string };

/**
 * Form Inertia + UI state du modal de sortie de flotte. Le composable :
 *
 *   - construit la liste d'options du motif de sortie
 *   - calcule `today` (max sur le DateInput)
 *   - calcule `canSubmit` (date + motif obligatoires)
 *   - applique `payloadTransform` qui mappe la note vide vers null
 *   - submit POST /vehicles/{id}/exit puis ferme la modale
 *
 * En cas de conflits (contrats/indispos qui débordent la date proposée),
 * le backend lève {@link App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException}
 * convertie en flash `toast-error` par le handler global. La modale reste
 * ouverte avec les inputs préservés (pattern back()->withInput()).
 */
export function useExitVehicleForm(
    props: { vehicleId: number },
    open: Ref<boolean>,
): {
    reasonOptions: SelectOption[];
    today: string;
    form: InertiaForm<FormShape>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const reasonOptions: SelectOption[] = (
        Object.keys(vehicleExitReasonLabel) as VehicleExitReason[]
    ).map((value) => ({
        value,
        label: vehicleExitReasonLabel[value],
    }));

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm<FormShape>({
        exit_date: today,
        exit_reason: '',
        note: '',
    });

    watch(open, (value) => {
        if (value) {
            form.reset();
            form.exit_date = today;
            form.exit_reason = '';
            form.note = '';
            form.clearErrors();
        }
    });

    const canSubmit = computed<boolean>(() => {
        return form.exit_date !== '' && form.exit_reason !== '';
    });

    const payloadTransform = (data: FormShape): Record<string, unknown> => ({
        exit_date: data.exit_date,
        exit_reason: data.exit_reason,
        note: data.note === '' ? null : data.note,
    });

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        form.transform(payloadTransform).post(
            vehiclesExitRoute.url({ vehicle: props.vehicleId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                },
            },
        );
    };

    return {
        reasonOptions,
        today,
        form,
        canSubmit,
        submit,
    };
}
