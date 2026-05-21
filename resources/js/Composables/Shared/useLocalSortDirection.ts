/**
 * Page-local sort-direction selector for views that have a single fixed sort key
 * (e.g. Planning Index / Per Company sort by vehicle license plate).
 *
 * Mirrors the API of `useLocalYearSelector`: writes `?direction=asc|desc` to the URL while
 * preserving every other query param (year, filters…), and triggers an Inertia partial
 * reload of the props the page lists in `only`.
 *
 * For Index pages using `useServerTableState`, prefer that composable (multi-key sort
 * with pagination + filters bundled). This one is reserved to standalone views.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export type SortDirection = 'asc' | 'desc';

export function useLocalSortDirection(
    initialDirection: SortDirection,
    only: readonly string[],
    options: { onSuccess?: () => void } = {},
): {
    direction: Ref<SortDirection>;
    toggle: () => void;
} {
    const direction = ref<SortDirection>(initialDirection);

    function toggle(): void {
        const next: SortDirection = direction.value === 'asc' ? 'desc' : 'asc';
        direction.value = next;

        const url = new URL(window.location.href);
        url.searchParams.set('direction', next);

        router.get(
            url.pathname + url.search,
            {},
            {
                only: [...only],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: options.onSuccess,
            },
        );
    }

    return { direction, toggle };
}
