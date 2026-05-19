import { router } from '@inertiajs/vue3';
import { onClickOutside, onKeyStroke } from '@vueuse/core';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { logout as logoutRoute } from '@/routes';

/**
 * State and handlers for the user menu (avatar in the top bar): open/close, logout.
 *
 * Accepts the root template ref to wire `onClickOutside` (the ref must be declared
 * in the component because it points to a template element) and installs the Escape listener.
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
        // `preserveScroll: true` avoids the visual jump during the transition to the login page
        // (the dashboard scroll was resetting to 0 on POST).
        // `preserveState: false` resets the client Inertia state (visited tabs, in-flight forms, etc.)
        // on logout: closed user session = mandatory fresh state.
        router.post(logoutRoute.url(), {}, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    onClickOutside(rootRef, close);

    onKeyStroke('Escape', () => {
        if (open.value) {
            close();
        }
    });

    return { open, close, toggle, logout };
}
