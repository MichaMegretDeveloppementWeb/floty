import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * UI state for the action modals on the Vehicle Show page (Exit fleet / Reactivate).
 *
 * No business logic: each modal ships its own Inertia form composable. This composable
 * only manages open/close to keep VehicleHeader.vue minimal.
 */
export function useVehicleShowActions(): {
    exitModalOpen: Ref<boolean>;
    reactivateModalOpen: Ref<boolean>;
    openExit: () => void;
    openReactivate: () => void;
} {
    const exitModalOpen = ref<boolean>(false);
    const reactivateModalOpen = ref<boolean>(false);

    const openExit = (): void => {
        exitModalOpen.value = true;
    };

    const openReactivate = (): void => {
        reactivateModalOpen.value = true;
    };

    return {
        exitModalOpen,
        reactivateModalOpen,
        openExit,
        openReactivate,
    };
}
