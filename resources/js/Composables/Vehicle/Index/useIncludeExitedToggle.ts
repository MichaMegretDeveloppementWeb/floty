import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Ref, WritableComputedRef } from 'vue';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';

/**
 * Toggle « Inclure véhicules retirés » sur la page Index Flotte.
 *
 * Le filtre est appliqué côté backend (cf. ADR-0018 § 4) - le repo
 * filtre via `Vehicle::activeAt(today)` quand `includeExited === false`.
 * Le toggle déclenche donc un Inertia visit qui re-fetch la liste avec
 * la query `?include_exited=1`.
 *
 * `preserveScroll` + `preserveState: false` car la liste change : on
 * accepte de réinitialiser le tri/filtre client (l'URL en porte déjà
 * la sérialisation via `useTableState`).
 */
export function useIncludeExitedToggle(
    propValue: Ref<boolean>,
): {
    includeExited: WritableComputedRef<boolean>;
} {
    const includeExited = computed<boolean>({
        get: () => propValue.value,
        set: (value: boolean) => {
            router.visit(vehiclesIndexRoute.url(), {
                data: value ? { include_exited: 1 } : {},
                preserveScroll: true,
                preserveState: false,
                replace: true,
            });
        },
    });

    return { includeExited };
}
