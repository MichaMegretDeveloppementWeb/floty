import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { fullYearTax as fullYearTaxRoute } from '@/routes/user/vehicles';

/**
 * Full-year tax for a vehicle on the year derived from `startDate` (fallback to current year).
 *
 * Triggered on demand by the Create/Edit Contract form when the user picks a vehicle or
 * changes the start date. Displayed as a discreet hint under the vehicle selector.
 *
 * Backend: `GET /app/vehicles/{vehicle}/full-year-tax?year=YYYY`
 * Service: {@see VehicleListingService::fullYearTaxForVehicle}
 *
 * Debounced reactive watch (200 ms) on `vehicleId` + `startDate`: one call per real user choice,
 * not one per keystroke. Cleanup via `onScopeDispose` so no leak if the parent unmounts mid-debounce.
 *
 * Result semantics:
 *   - `cents`: full-year tax in cents (integer)
 *   - `year`: year actually computed (may differ from requested if fallback to a known neighbour)
 *   - `fallback`: true iff `year` differs from `requestedYear`
 */
export type VehicleFullYearTaxResult = {
    cents: number | null;
    year: number;
    fallback: boolean;
};

export type UseVehicleFullYearTaxReturn = {
    result: Ref<VehicleFullYearTaxResult | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

const DEBOUNCE_MS = 200;

export function useVehicleFullYearTax(opts: {
    vehicleId: Ref<number | null>;
    /** ISO `YYYY-MM-DD` date (from `form.start_date`). Empty = current year. */
    startDate: Ref<string>;
}): UseVehicleFullYearTaxReturn {
    const api = useApi();
    const result = ref<VehicleFullYearTaxResult | null>(null);
    const loading = ref(false);
    let debounceHandle: number | null = null;

    const targetYear = computed<number>(() => {
        const s = opts.startDate.value;

        if (s && s.length >= 4) {
            const y = Number.parseInt(s.slice(0, 4), 10);

            if (Number.isFinite(y)) {
                return y;
            }
        }

        return new Date().getFullYear();
    });

    const reset = (): void => {
        if (debounceHandle !== null) {
            window.clearTimeout(debounceHandle);
            debounceHandle = null;
        }

        result.value = null;
        loading.value = false;
    };

    watch(
        () => [opts.vehicleId.value, targetYear.value] as const,
        ([vehicleId, year]) => {
            if (debounceHandle !== null) {
                window.clearTimeout(debounceHandle);
            }

            if (vehicleId === null) {
                result.value = null;

                return;
            }

            debounceHandle = window.setTimeout(async () => {
                loading.value = true;

                try {
                    result.value = await api.get<VehicleFullYearTaxResult>(
                        fullYearTaxRoute.url({ vehicle: vehicleId }),
                        { year },
                    );
                } catch {
                    result.value = null;
                } finally {
                    loading.value = false;
                }
            }, DEBOUNCE_MS);
        },
        { immediate: true },
    );

    onScopeDispose(reset);

    return { result, loading, reset };
}
