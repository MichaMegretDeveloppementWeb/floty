import { router } from '@inertiajs/vue3';
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { logout as logoutRoute } from '@/routes';

/**
 * État + handlers du menu utilisateur (avatar dans le top-bar) :
 * ouverture/fermeture, déconnexion. Le composable accepte le
 * template ref racine pour câbler le `onClickOutside` (le ref doit
 * être déclaré dans le composant car il pointe vers un élément du
 * template), et installe le listener Escape pour fermer.
 */
export function useUserMenu(rootRef: Readonly<Ref<HTMLElement | null>>): {
    open: Ref<boolean>;
    close: () => void;
    toggle: () => void;
    logout: () => void;
} {
    const open = ref<boolean>(false);

    const close = (): void => {
        open.value = false;
    };

    const toggle = (): void => {
        open.value = !open.value;
    };

    const logout = (): void => {
        close();
        router.post(logoutRoute.url());
    };

    onClickOutside(rootRef, close);

    onKeyStroke('Escape', () => {
        if (open.value) {
            close();
        }
    });

    return { open, close, toggle, logout };
}
