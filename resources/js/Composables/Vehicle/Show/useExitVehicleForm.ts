import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import { exit as vehiclesExitRoute } from '@/routes/user/vehicles';
import { eurosToCents } from '@/Utils/format/money';
import { vehicleExitReasonLabel } from '@/Utils/labels/vehicleEnumLabels';

type VehicleExitReason = App.Enums.Vehicle.VehicleExitReason;

type FormShape = {
    exit_date: string;
    exit_reason: VehicleExitReason | '';
    note: string;
    // Optional exit cost in EUROS (converted to amount_cents on submit).
    amount: number | null;
};

type SelectOption = { value: VehicleExitReason; label: string };

/**
 * Inertia form + UI state for the fleet-exit modal.
 *
 *   - builds the exit-reason options list
 *   - computes `today` (max on the DateInput)
 *   - computes `canSubmit` (date + reason required)
 *   - applies `payloadTransform` mapping empty note → null
 *   - submits POST /vehicles/{id}/exit then closes the modal
 *
 * On conflicts (contracts/unavailabilities that overflow the proposed date), the backend raises
 * {@link App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException}, converted to a flash
 * `toast-error` by the global handler. The modal stays open with preserved inputs
 * (back()->withInput() pattern).
 */
export function useExitVehicleForm(
    props: { vehicleId: number },
    open: Ref<boolean>,
): {
    reasonOptions: SelectOption[];
    today: string;
    form: InertiaForm<FormShape>;
    canSubmit: ComputedRef<boolean>;
    /** Server error for the amount (payload key `amount_cents`, outside FormShape). */
    amountError: ComputedRef<string | undefined>;
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
        amount: null,
    });

    watch(open, (value) => {
        if (value) {
            form.reset();
            form.exit_date = today;
            form.exit_reason = '';
            form.note = '';
            form.amount = null;
            form.clearErrors();
        }
    });

    const amountError = computed<string | undefined>(
        () => (form.errors as Record<string, string | undefined>).amount_cents,
    );

    const canSubmit = computed<boolean>(() => {
        return form.exit_date !== '' && form.exit_reason !== '';
    });

    const payloadTransform = (data: FormShape): Record<string, unknown> => ({
        exit_date: data.exit_date,
        exit_reason: data.exit_reason,
        note: data.note === '' ? null : data.note,
        amount_cents: eurosToCents(data.amount),
    });

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        form.transform(payloadTransform).post(
            vehiclesExitRoute.url({ vehicle: props.vehicleId }),
            closeOnSuccess(open),
        );
    };

    return {
        reasonOptions,
        today,
        form,
        canSubmit,
        amountError,
        submit,
    };
}
