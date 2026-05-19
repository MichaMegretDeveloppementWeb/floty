import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Open/closed state for a drawer or modal.
 *
 * Minimal API: no body-scroll-lock and no focus-trap (deferred to a future version with @headlessui/vue).
 */
export type UseDrawerReturn = {
    isOpen: Ref<boolean>;
    open: () => void;
    close: () => void;
    toggle: () => void;
};

export function useDrawer(initial = false): UseDrawerReturn {
    const isOpen = ref(initial);

    return {
        isOpen,
        open: () => {
            isOpen.value = true;
        },
        close: () => {
            isOpen.value = false;
        },
        toggle: () => {
            isOpen.value = !isOpen.value;
        },
    };
}
