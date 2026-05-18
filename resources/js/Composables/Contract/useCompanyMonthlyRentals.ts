import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { monthlyRentals as monthlyRentalsRoute } from '@/routes/user/companies';

/**
 * 12 montants mensuels NET (post-réductions) pour une entreprise ×
 * année · consommé par le form Create/Edit Contract pour afficher une
 * mini-timeline 12 mois dans le récap (SC9 · 2026-05-18).
 *
 * Backend · `GET /app/companies/{company}/monthly-rentals?year=YYYY`
 *
 * Watch reactif debouncé (200 ms) sur `companyId` + année dérivée de
 * `startDate`. Cleanup `onScopeDispose`. Pattern strictement identique
 * à `useVehicleFullYearTax` / `useVehicleYearlyRates`.
 *
 * Sémantique · `rentals[month]` = totalCents net post-discount, `null`
 * si au moins un véhicule présent sur ce mois n'a pas de tarif annuel
 * saisi (signal UX explicite côté mini-timeline · tiret discret).
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
    /** Date ISO `YYYY-MM-DD` (de `form.start_date`). Vide = année courante. */
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
