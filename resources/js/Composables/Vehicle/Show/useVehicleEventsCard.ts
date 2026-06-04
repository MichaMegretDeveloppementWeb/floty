import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { destroy as vehicleEventsDestroyRoute } from '@/routes/user/vehicle-events';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

/**
 * State and handlers for the vehicle events card: drives the create/edit form modal,
 * the delete confirmation modal, and the Inertia DELETE request.
 */
export function useVehicleEventsCard(): {
    formOpen: Ref<boolean>;
    editing: Ref<VehicleEvent | null>;
    confirmOpen: Ref<boolean>;
    deleting: Ref<VehicleEvent | null>;
    openCreate: () => void;
    openEdit: (item: VehicleEvent) => void;
    askDelete: (item: VehicleEvent) => void;
    confirmDelete: () => void;
    formatPeriod: (item: VehicleEvent) => string;
} {
    const formOpen = ref<boolean>(false);
    const editing = ref<VehicleEvent | null>(null);

    const confirmOpen = ref<boolean>(false);
    const deleting = ref<VehicleEvent | null>(null);

    const openCreate = (): void => {
        editing.value = null;
        formOpen.value = true;
    };

    const openEdit = (item: VehicleEvent): void => {
        editing.value = item;
        formOpen.value = true;
    };

    const askDelete = (item: VehicleEvent): void => {
        deleting.value = item;
        confirmOpen.value = true;
    };

    const confirmDelete = (): void => {
        if (!deleting.value) {
            return;
        }

        router.delete(
            vehicleEventsDestroyRoute.url({
                vehicleEvent: deleting.value.id,
            }),
            {
                preserveScroll: true,
                onFinish: () => {
                    confirmOpen.value = false;
                    deleting.value = null;
                },
            },
        );
    };

    const formatPeriod = (item: VehicleEvent): string => {
        const start = formatDateFr(item.startDate);

        if (item.endDate === null) {
            return `Depuis le ${start} (en cours)`;
        }

        return `Du ${start} au ${formatDateFr(item.endDate)}`;
    };

    return {
        formOpen,
        editing,
        confirmOpen,
        deleting,
        openCreate,
        openEdit,
        askDelete,
        confirmDelete,
        formatPeriod,
    };
}
