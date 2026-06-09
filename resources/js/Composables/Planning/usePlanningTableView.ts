import { computed, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { SortDirection } from '@/Composables/Shared/useLocalSortDirection';

/** Minimal shape both heatmap vehicle DTO variants share. */
type SearchableVehicle = {
    licensePlate: string;
    brand: string;
    model: string;
};

/** Lowercase + strip diacritics so « Citroën » matches « citroen ». */
function normalizeForSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

/**
 * Client-side filter + sort for the planning heatmap.
 *
 * The heatmap loads the whole vehicle list eagerly and the
 * plate/brand/model are already in the payload, so filtering and sorting
 * happen in memory · instant, no Inertia round-trip, no skeleton, no
 * hidden load. The `?search=` / `?direction=` query params are mirrored to
 * the URL via `history.replaceState` (no reload) so deep-links are
 * preserved and the filter survives the year-change partial reload (the
 * list prop changes, this computed re-applies the current search + sort).
 *
 * Works for both `PlanningHeatmapVehicleData` and
 * `PlanningHeatmapCompanyVehicleData` (both expose the three searched
 * fields).
 */
export function usePlanningTableView<T extends SearchableVehicle>(
    vehicles: Readonly<Ref<readonly T[]>>,
    opts: { initialDirection: SortDirection },
): {
    search: Ref<string>;
    direction: Ref<SortDirection>;
    displayedVehicles: ComputedRef<T[]>;
    toggleSort: () => void;
} {
    const search = ref<string>('');
    const direction = ref<SortDirection>(opts.initialDirection);

    function readSearchFromUrl(): string {
        if (typeof window === 'undefined') {
            return '';
        }

        return new URLSearchParams(window.location.search).get('search') ?? '';
    }

    function writeToUrl(): void {
        if (typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);
        const term = search.value.trim();

        if (term === '') {
            url.searchParams.delete('search');
        } else {
            url.searchParams.set('search', term);
        }

        url.searchParams.set('direction', direction.value);
        window.history.replaceState({}, '', url.toString());
    }

    onMounted(() => {
        search.value = readSearchFromUrl();
    });

    watch([search, direction], () => writeToUrl());

    const displayedVehicles = computed<T[]>(() => {
        const term = normalizeForSearch(search.value.trim());

        const filtered =
            term === ''
                ? [...vehicles.value]
                : vehicles.value.filter(
                      (vehicle) =>
                          normalizeForSearch(vehicle.licensePlate).includes(
                              term,
                          ) ||
                          normalizeForSearch(vehicle.brand).includes(term) ||
                          normalizeForSearch(vehicle.model).includes(term),
                  );

        const factor = direction.value === 'asc' ? 1 : -1;

        return filtered.sort(
            (a, b) =>
                factor *
                a.licensePlate.localeCompare(b.licensePlate, 'fr', {
                    numeric: true,
                    sensitivity: 'base',
                }),
        );
    });

    function toggleSort(): void {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    }

    return { search, direction, displayedVehicles, toggleSort };
}
