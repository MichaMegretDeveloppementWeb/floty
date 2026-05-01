import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { reactivate as vehiclesReactivateRoute } from '@/routes/user/vehicles';

/**
 * Form Inertia + UI state du modal de réactivation. Aucun champ
 * utilisateur : la confirmation suffit, le backend remet
 * `exit_date = NULL`, `exit_reason = NULL`, `current_status = active`.
 *
 * Pas de cas d'erreur métier (réactiver un véhicule actif est idempotent
 * côté Action). Le composable expose juste le submit pour un bouton de
 * confirmation à un seul clic.
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
        form.post(vehiclesReactivateRoute.url({ vehicle: props.vehicleId }), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    };

    return { form, submit };
}
