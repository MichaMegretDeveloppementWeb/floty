import { ref, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * UI state for the action modals on the Vehicle Show page (Exit fleet / Reactivate).
 *
 * No business logic: each modal ships its own Inertia form composable. This composable
 * only manages open/close to keep VehicleHeader.vue minimal.
 *
 * The Exit and Reactivate modals are mutually exclusive (rendered via `v-if` on
 * `vehicle.isExited`). After a successful exit/reactivation the Inertia visit flips
 * `isExited` and remounts the opposite modal; we reset BOTH open refs on that flip so a
 * stale `open = true` left over from the just-closed modal can't resurface on the one
 * that remounts (otherwise confirming a reactivation would pop the exit modal open, and
 * vice versa).
 */
export function useVehicleShowActions(isExited: () => boolean): {
    exitModalOpen: Ref<boolean>;
    reactivateModalOpen: Ref<boolean>;
    openExit: () => void;
    openReactivate: () => void;
} {
    const exitModalOpen = ref<boolean>(false);
    const reactivateModalOpen = ref<boolean>(false);

    watch(isExited, () => {
        exitModalOpen.value = false;
        reactivateModalOpen.value = false;
    });

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
