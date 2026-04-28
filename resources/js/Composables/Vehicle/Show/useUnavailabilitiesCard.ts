import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { destroy as unavailabilitiesDestroyRoute } from '@/routes/user/unavailabilities';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Unavailability = App.Data.User.Unavailability.UnavailabilityData;

/**
 * État + handlers de la card Indisponibilités : pilote l'ouverture
 * du modal de form (création/édition), du modal de confirmation de
 * suppression, et la requête DELETE Inertia.
 */
export function useUnavailabilitiesCard(): {
    formOpen: Ref<boolean>;
    editing: Ref<Unavailability | null>;
    confirmOpen: Ref<boolean>;
    deleting: Ref<Unavailability | null>;
    openCreate: () => void;
    openEdit: (item: Unavailability) => void;
    askDelete: (item: Unavailability) => void;
    confirmDelete: () => void;
    formatPeriod: (item: Unavailability) => string;
} {
    const formOpen = ref<boolean>(false);
    const editing = ref<Unavailability | null>(null);

    const confirmOpen = ref<boolean>(false);
    const deleting = ref<Unavailability | null>(null);

    const openCreate = (): void => {
        editing.value = null;
        formOpen.value = true;
    };

    const openEdit = (item: Unavailability): void => {
        editing.value = item;
        formOpen.value = true;
    };

    const askDelete = (item: Unavailability): void => {
        deleting.value = item;
        confirmOpen.value = true;
    };

    const confirmDelete = (): void => {
        if (!deleting.value) {
            return;
        }

        router.delete(
            unavailabilitiesDestroyRoute.url({
                unavailability: deleting.value.id,
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

    const formatPeriod = (item: Unavailability): string => {
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
