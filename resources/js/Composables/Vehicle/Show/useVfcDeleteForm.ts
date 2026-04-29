import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { VfcDeleteFormShape } from '@/pages/User/Vehicles/Show/forms';
import { destroy as vfcDestroyRoute } from '@/routes/user/vehicle-fiscal-characteristics';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ExtensionStrategy = App.Enums.Vehicle.FiscalCharacteristicsExtensionStrategy;

/**
 * Form Inertia + UI state du modal de suppression d'une VFC.
 *
 * L'utilisateur doit choisir explicitement la stratégie de
 * comblement du trou laissé par la suppression :
 *   - `extend_previous` : la version précédente est étendue jusqu'à
 *     la fin de la période supprimée,
 *   - `extend_next` : la version suivante est reculée pour démarrer
 *     au début de la période supprimée.
 *
 * Le backend valide qu'au moins une des deux stratégies est
 * applicable selon le contexte (présence d'un voisin compatible).
 */
export function useVfcDeleteForm(
    props: { deleting: Vfc | null },
    open: Ref<boolean>,
): {
    form: InertiaForm<VfcDeleteFormShape>;
    strategyOptions: { value: ExtensionStrategy; label: string }[];
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const form = useForm<VfcDeleteFormShape>({
        extension_strategy: '',
    });

    const strategyOptions: { value: ExtensionStrategy; label: string }[] = [
        { value: 'extend_previous', label: 'Étendre la version précédente sur la période supprimée' },
        { value: 'extend_next', label: 'Étendre la version suivante sur la période supprimée' },
    ];

    watch(
        () => props.deleting,
        () => {
            form.reset();
            form.clearErrors();
        },
    );

    const canSubmit = computed<boolean>(
        () => form.extension_strategy !== '',
    );

    const submit = (): void => {
        if (!canSubmit.value || !props.deleting) {
            return;
        }

        form.transform((data) => ({
            extension_strategy: data.extension_strategy as ExtensionStrategy,
        })).delete(
            vfcDestroyRoute.url({ vehicleFiscalCharacteristic: props.deleting.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                },
            },
        );
    };

    return {
        form,
        strategyOptions,
        canSubmit,
        submit,
    };
}

/**
 * Helper utilisé par le partial pour ouvrir/fermer la modale et
 * traquer la VFC en cours de suppression.
 */
export function useVfcDeleteModalState(): {
    open: Ref<boolean>;
    deleting: Ref<Vfc | null>;
    requestDelete: (vfc: Vfc) => void;
} {
    const open = ref<boolean>(false);
    const deleting = ref<Vfc | null>(null);

    const requestDelete = (vfc: Vfc): void => {
        deleting.value = vfc;
        open.value = true;
    };

    return { open, deleting, requestDelete };
}
