import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * État ouvert/fermé d'un drawer ou d'une modale.
 *
 * API minimaliste pour le moment — pas de body-scroll-lock ni
 * focus-trap (à ajouter en V1.5+ avec @headlessui/vue).
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
