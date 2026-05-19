import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Data derived from `usePage()` (authenticated user) for the top navigation: full name and initials.
 *
 * The search field is no longer exposed here: it is carried by the global `<CommandPalette>`
 * (see {@see useCommandPalette}). The `TopBar` only renders a trigger button calling `palette.open()`.
 */
export function useTopBar(): {
    fullName: ComputedRef<string>;
    initials: ComputedRef<string>;
} {
    const page = usePage();
    const authUser = computed(() => page.props.auth?.user ?? null);

    const fullName = computed<string>(() => {
        const user = authUser.value;

        if (!user) {
            return 'Invité';
        }

        return user.fullName || 'Utilisateur';
    });

    const initials = computed<string>(() => {
        const user = authUser.value;

        if (!user) {
            return '?';
        }

        const first = user.firstName?.[0] ?? '';
        const last = user.lastName?.[0] ?? '';

        return (first + last).toUpperCase() || '?';
    });

    return { fullName, initials };
}
