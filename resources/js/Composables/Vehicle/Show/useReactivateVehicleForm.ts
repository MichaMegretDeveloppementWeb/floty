import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import { reactivate as vehiclesReactivateRoute } from '@/routes/user/vehicles';

/**
 * Inertia form + UI state for the reactivation modal.
 *
 * No user field: a confirmation suffices and the backend resets `exit_date = NULL`,
 * `exit_reason = NULL`, `current_status = active`.
 *
 * No business error case (reactivating an active vehicle is idempotent in the Action).
 * The composable just exposes submit for a single-click confirmation button.
 */
export function useReactivateVehicleForm(
    props: { vehicleId: number },
    open: Ref<boolean>,
): {
    form: InertiaForm<Record<string, never>>;
    submit: () => void;
} {
    const form = useForm<Record<string, never>>({});

    const submit = (): void => {
        form.post(
            vehiclesReactivateRoute.url({ vehicle: props.vehicleId }),
            closeOnSuccess(open),
        );
    };

    return { form, submit };
}
