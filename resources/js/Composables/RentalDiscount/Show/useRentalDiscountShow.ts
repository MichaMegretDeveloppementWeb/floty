/**
 * Composable Show RentalDiscount (Lot 4 du chantier).
 * Centralise l'état des modales (delete) et les actions.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { destroy as destroyRoute } from '@/routes/user/rental-discounts';

export function useRentalDiscountShow(): {
    deleteModalOpen: Ref<boolean>;
    openDelete: () => void;
    closeDelete: () => void;
    confirmDelete: (id: number) => void;
} {
    const deleteModalOpen = ref<boolean>(false);

    const openDelete = (): void => {
        deleteModalOpen.value = true;
    };

    const closeDelete = (): void => {
        deleteModalOpen.value = false;
    };

    const confirmDelete = (id: number): void => {
        router.delete(destroyRoute.url({ rentalDiscount: id }), {
            preserveScroll: false,
            onSuccess: () => {
                deleteModalOpen.value = false;
            },
        });
    };

    return {
        deleteModalOpen,
        openDelete,
        closeDelete,
        confirmDelete,
    };
}
