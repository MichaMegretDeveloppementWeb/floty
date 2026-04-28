import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/**
 * Données dérivées du `usePage()` (utilisateur authentifié) pour la
 * barre de navigation : nom complet et initiales (avatar). État local
 * du champ de recherche également exposé ici.
 */
export function useTopBar(): {
    search: Ref<string>;
    fullName: ComputedRef<string>;
    initials: ComputedRef<string>;
} {
    const search = ref<string>('');

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

    return { search, fullName, initials };
}
