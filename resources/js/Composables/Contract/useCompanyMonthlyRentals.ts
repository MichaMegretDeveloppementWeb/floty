import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { monthlyRentals as monthlyRentalsRoute } from '@/routes/user/companies';

/**
 * 12 monthly NET amounts (post-discount) for one company × year.
 *
 * Consumed by the Create/Edit Contract form to render a 12-month mini-timeline in the recap.
 *
 * Backend: `GET /app/companies/{company}/monthly-rentals?year=YYYY`
 *
 * Debounced reactive watch (200 ms) on `companyId` + year derived from `startDate`.
 * Cleanup via `onScopeDispose`. Same pattern as `useVehicleFullYearTax` / `useVehicleYearlyRates`.
 *
 * `rentals[month]` = net totalCents post-discount, `null` if at least one vehicle present that
 * month has no annual rate set (explicit UX signal on the mini-timeline).
 */
export type CompanyMonthlyRentalsResult = {
    year: number;
    rentals: Record<number, number | null>;
};

export type UseCompanyMonthlyRentalsReturn = {
    result: Ref<CompanyMonthlyRentalsResult | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

const DEBOUNCE_MS = 200;

export function useCompanyMonthlyRentals(opts: {
    companyId: Ref<number | null>;
    /** ISO `YYYY-MM-DD` date (from `form.start_date`). Empty = current year. */
    startDate: Ref<string>;
}): UseCompanyMonthlyRentalsReturn {
    const api = useApi();
    const result = ref<CompanyMonthlyRentalsResult | null>(null);
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
        () => [opts.companyId.value, targetYear.value] as const,
        ([companyId, year]) => {
            if (debounceHandle !== null) {
                window.clearTimeout(debounceHandle);
            }

            if (companyId === null) {
                result.value = null;

                return;
            }

            debounceHandle = window.setTimeout(async () => {
                loading.value = true;

                try {
                    result.value = await api.get<CompanyMonthlyRentalsResult>(
                        monthlyRentalsRoute.url({ company: companyId }),
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
