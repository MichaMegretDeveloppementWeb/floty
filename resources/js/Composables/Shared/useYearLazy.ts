/**
 * Generic year-by-year loader with client cache.
 *
 * Pattern: a card/section carries year-parameterised data (e.g. Timeline + Breakdown for a vehicle).
 * The initial year ships in the page's standard Inertia payload. When the user changes the year:
 *
 *   1. Check the local `Map<year, T>` cache.
 *   2. Cache hit → immediate render, zero round-trip.
 *   3. Cache miss → targeted JSON fetch via `fetchFn(year)`, store in cache, render.
 *      `isLoading: true` while waiting.
 *
 * Avoids the backend pre-computing every year while keeping an instant selector UX for visited years.
 *
 * No URL sync: F5 falls back on the initial year. For a tab whose year must survive F5 or link sharing,
 * use the `YearPills` + Inertia partial reload pattern instead
 * (`useVehicleFiscalSelectedYear` / `useCompanyFiscalSelectedYear`).
 */

import { computed, ref } from 'vue';
import type { Ref, WritableComputedRef } from 'vue';

export type UseYearLazyReturn<T> = {
    /** Currently displayed year. */
    year: Ref<number>;
    /** `v-model` wrapper that routes the setter through `selectYear()`. */
    yearModel: WritableComputedRef<number>;
    /** Data for `year.value` (or `null` during the initial fetch). */
    data: Ref<T | null>;
    /** `true` while a fetch is in progress. */
    isLoading: Ref<boolean>;
    /** Last fetch error if any. */
    error: Ref<string | null>;
    /** Explicit year switch. */
    selectYear: (year: number) => Promise<void>;
    /**
     * Clears the cache and refetches the current year. For `initialYear`, pass `freshInitial`
     * to replace the data directly without a round-trip (typically when the parent has a new Inertia
     * payload post-CRUD and wants to propagate fresh stats without blocking the UI).
     */
    invalidate: (freshInitial?: T) => Promise<void>;
};

export function useYearLazy<T>(
    initialYear: number,
    initialData: T,
    fetchFn: (year: number) => Promise<T>,
): UseYearLazyReturn<T> {
    const year = ref<number>(initialYear);
    const cache = new Map<number, T>([[initialYear, initialData]]);
    const data = ref<T | null>(initialData) as Ref<T | null>;
    const isLoading = ref<boolean>(false);
    const error = ref<string | null>(null);

    async function selectYear(target: number): Promise<void> {
        if (target === year.value) {
            return;
        }

        const cached = cache.get(target);

        if (cached !== undefined) {
            year.value = target;
            data.value = cached;

            return;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchFn(target);
            cache.set(target, fetched);
            year.value = target;
            data.value = fetched;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur inconnue';
        } finally {
            isLoading.value = false;
        }
    }

    const yearModel = computed<number>({

        get: () => year.value,
        set: (value: number) => {
            void selectYear(value);
        },
    });

    async function invalidate(freshInitial?: T): Promise<void> {
        cache.clear();

        if (freshInitial !== undefined) {
            cache.set(initialYear, freshInitial);

            if (year.value === initialYear) {
                data.value = freshInitial;

                return;
            }
        }

        // Refetch the current year (may differ from initialYear if the user switched before the parent CRUD).
        const target = year.value;

        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchFn(target);
            cache.set(target, fetched);
            data.value = fetched;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur inconnue';
        } finally {
            isLoading.value = false;
        }
    }

    return { year, yearModel, data, isLoading, error, selectYear, invalidate };
}
