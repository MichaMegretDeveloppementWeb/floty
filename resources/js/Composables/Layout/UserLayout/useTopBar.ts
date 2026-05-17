import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Données dérivées du `usePage()` (utilisateur authentifié) pour la
 * barre de navigation · nom complet et initiales (avatar).
 *
 * Le champ de recherche n'est plus exposé ici · depuis V1.1 il est
 * porté par la palette globale `<CommandPalette>` (cf.
 * {@see useCommandPalette}). Le `TopBar` ne contient qu'un trigger
 * (button) qui appelle `palette.open()`.
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
