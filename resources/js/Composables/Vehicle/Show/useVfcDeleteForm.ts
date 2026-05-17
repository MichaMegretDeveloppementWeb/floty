import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import type { VfcDeleteFormShape } from '@/pages/User/Vehicles/Show/forms';
import { destroy as vfcDestroyRoute } from '@/routes/user/vehicle-fiscal-characteristics';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ExtensionStrategy = App.Enums.Vehicle.FiscalCharacteristicsExtensionStrategy;

/**
 * Form Inertia + UI state du modal de suppression d'une VFC.
 *
 * L'utilisateur doit choisir explicitement la stratégie de
 * comblement du trou laissé par la suppression ·
 *   - `extend_previous` · la version précédente est étendue jusqu'à
 *     la fin de la période supprimée,
 *   - `extend_next` · la version suivante est reculée pour démarrer
 *     au début de la période supprimée.
 *
 * P8 · le filtrage des stratégies se fait côté frontend selon la
 * présence d'un voisin compatible dans l'historique ·
 *   - VFC seule de l'historique · aucune stratégie possible (le
 *     backend lèverait `CannotDeleteOnlyVersionException`), le modal
 *     affiche un message bloquant et désactive le submit.
 *   - Plus ancienne VFC (pas de précédente) · seul `extend_next` est
 *     proposé.
 *   - Plus récente VFC (pas de suivante) · seul `extend_previous`
 *     est proposé.
 *   - VFC encadrée · les deux stratégies sont proposées.
 *
 * Sans ce filtrage, l'utilisateur pouvait choisir une stratégie
 * inapplicable et obtenir une exception backend après soumission.
 */
export function useVfcDeleteForm(
    props: { deleting: Vfc | null; history: ReadonlyArray<Vfc> },
    open: Ref<boolean>,
): {
    form: InertiaForm<VfcDeleteFormShape>;
    strategyOptions: ComputedRef<{ value: ExtensionStrategy; label: string }[]>;
    canSubmit: ComputedRef<boolean>;
    isOnlyVersion: ComputedRef<boolean>;
    submit: () => void;
} {
    const form = useForm<VfcDeleteFormShape>({
        extension_strategy: '',
    });

    /**
     * Détermine si la VFC en cours de suppression a un voisin temporel
     * (précédent ou suivant) dans l'historique. L'historique est trié
     * par `effective_from` ASC côté backend (`findByVehicle`), on
     * s'appuie sur cette invariante.
     */
    const hasPrevious = computed<boolean>(() => {
        if (props.deleting === null) {
            return false;
        }

        const currentFrom = props.deleting.effectiveFrom;

        return props.history.some((v) => v.id !== props.deleting!.id && v.effectiveFrom < currentFrom);
    });

    const hasNext = computed<boolean>(() => {
        if (props.deleting === null) {
            return false;
        }

        const currentFrom = props.deleting.effectiveFrom;

        return props.history.some((v) => v.id !== props.deleting!.id && v.effectiveFrom > currentFrom);
    });

    const isOnlyVersion = computed<boolean>(() => {
        if (props.deleting === null) {
            return false;
        }

        return ! hasPrevious.value && ! hasNext.value;
    });

    const strategyOptions = computed<{ value: ExtensionStrategy; label: string }[]>(() => {
        const options: { value: ExtensionStrategy; label: string }[] = [];

        if (hasPrevious.value) {
            options.push({
                value: 'extend_previous',
                label: 'Étendre la version précédente sur la période supprimée',
            });
        }

        if (hasNext.value) {
            options.push({
                value: 'extend_next',
                label: 'Étendre la version suivante sur la période supprimée',
            });
        }

        return options;
    });

    // Pré-sélection automatique de la seule option disponible · UX
    // pragmatique quand un seul choix est applicable, l'utilisateur
    // n'a plus qu'à confirmer.
    watch(
        () => props.deleting,
        () => {
            form.reset();
            form.clearErrors();
            const options = strategyOptions.value;

            if (options.length === 1) {
                form.extension_strategy = options[0]!.value;
            }
        },
    );

    const canSubmit = computed<boolean>(
        () => ! isOnlyVersion.value && form.extension_strategy !== '',
    );

    const submit = (): void => {
        if (! canSubmit.value || ! props.deleting) {
            return;
        }

        form.transform((data) => ({
            extension_strategy: data.extension_strategy as ExtensionStrategy,
        })).delete(
            vfcDestroyRoute.url({ vehicleFiscalCharacteristic: props.deleting.id }),
            closeOnSuccess(open),
        );
    };

    return {
        form,
        strategyOptions,
        canSubmit,
        isOnlyVersion,
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
