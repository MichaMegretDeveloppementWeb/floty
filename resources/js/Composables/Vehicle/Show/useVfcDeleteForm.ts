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
 * Inertia form + UI state for the VFC delete modal.
 *
 * The user must explicitly choose the gap-fill strategy left by the deletion:
 *   - `extend_previous`: the previous version is extended to the end of the deleted period,
 *   - `extend_next`: the next version moves earlier to start at the beginning of the deleted period.
 *
 * Strategy filtering happens client-side based on neighbour presence in the history:
 *   - Only VFC of the history: no strategy available (backend would raise `CannotDeleteOnlyVersionException`);
 *     the modal shows a blocking message and disables submit.
 *   - Oldest VFC (no predecessor): only `extend_next` is offered.
 *   - Newest VFC (no successor): only `extend_previous` is offered.
 *   - Bracketed VFC: both strategies are offered.
 *
 * Without this filtering, the user could pick an inapplicable strategy and hit a backend exception.
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
     * Determines whether the deleted VFC has a temporal neighbour (predecessor or successor) in the history.
     * The history is sorted by `effective_from` ASC by the backend (`findByVehicle`); we rely on that invariant.
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

    // Auto-select the only available option: pragmatic UX when a single choice applies; the user just confirms.
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
 * Helper used by the partial to open/close the modal and track the VFC being deleted.
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
