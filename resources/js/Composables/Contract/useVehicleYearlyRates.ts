import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { yearlyRates as yearlyRatesRoute } from '@/routes/user/vehicles';

/**
 * Daily/weekly/monthly yearly rates for a vehicle, on the year derived from `startDate`
 * (fallback to current year).
 *
 * Triggered on demand by the Create/Edit Contract form when the user picks a vehicle
 * or changes the start date.
 *
 * Backend: `GET /app/vehicles/{vehicle}/yearly-rates?year=YYYY`
 *
 * Debounced reactive watch (200 ms) on `vehicleId` + `startDate`; pattern identical
 * to `useVehicleFullYearTax`. Cleanup via `onScopeDispose`.
 *
 * `dailyCents`/`weeklyCents`/`monthlyCents` may be `null` when no rate was entered
 * for that year (UI shows a muted dash).
 */
export type VehicleYearlyRatesResult = {
    year: number;
    dailyCents: number | null;
    weeklyCents: number | null;
    monthlyCents: number | null;
};

export type UseVehicleYearlyRatesReturn = {
    result: Ref<VehicleYearlyRatesResult | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

const DEBOUNCE_MS = 200;

export function useVehicleYearlyRates(opts: {
    vehicleId: Ref<number | null>;
    /** ISO `YYYY-MM-DD` date (from `form.start_date`). Empty = current year. */
    startDate: Ref<string>;
}): UseVehicleYearlyRatesReturn {
    const api = useApi();
    const result = ref<VehicleYearlyRatesResult | null>(null);
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
                    result.value = await api.get<VehicleYearlyRatesResult>(
                        yearlyRatesRoute.url({ vehicle: vehicleId }),
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
