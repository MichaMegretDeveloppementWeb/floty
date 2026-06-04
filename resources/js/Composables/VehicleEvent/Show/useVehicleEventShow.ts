import { router } from '@inertiajs/vue3';
import { computed, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { destroy as vehicleEventsDestroyRoute } from '@/routes/user/vehicle-events';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

/**
 * State and derivations for the vehicle event detail page: the delete
 * confirmation modal, the DELETE request (the controller redirects to the
 * vehicle "Événements" timeline tab on success), and the human period label.
 */
export function useVehicleEventShow(event: MaybeRefOrGetter<VehicleEvent>): {
    deleteModalOpen: Ref<boolean>;
    deleting: Ref<boolean>;
    periodLabel: ComputedRef<string>;
    openDelete: () => void;
    closeDelete: () => void;
    confirmDelete: () => void;
} {
    const deleteModalOpen = ref<boolean>(false);
    const deleting = ref<boolean>(false);

    const openDelete = (): void => {
        deleteModalOpen.value = true;
    };

    const closeDelete = (): void => {
        deleteModalOpen.value = false;
    };

    const confirmDelete = (): void => {
        deleting.value = true;

        router.delete(
            vehicleEventsDestroyRoute.url({ vehicleEvent: toValue(event).id }),
            {
                onFinish: () => {
                    deleting.value = false;
                    deleteModalOpen.value = false;
                },
            },
        );
    };

    const periodLabel = computed<string>(() => {
        const current = toValue(event);
        const start = formatDateFr(current.startDate);

        if (current.endDate === null) {
            return `Depuis le ${start} (en cours)`;
        }

        return `Du ${start} au ${formatDateFr(current.endDate)}`;
    });

    return {
        deleteModalOpen,
        deleting,
        periodLabel,
        openDelete,
        closeDelete,
        confirmDelete,
    };
}
