import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { yearlyRates as yearlyRatesRoute } from '@/routes/user/vehicles';

/**
 * Tarifs annuels jour/semaine/mois d'un véhicule pour l'année dérivée de
 * `startDate` (fallback année courante). Déclenché à la volée par le
 * form Create/Edit Contract quand l'utilisateur sélectionne un véhicule
 * ou change la date de début (SC9 · 2026-05-18).
 *
 * Backend · `GET /app/vehicles/{vehicle}/yearly-rates?year=YYYY`
 *
 * Watch reactif debouncé (200 ms) sur `vehicleId` + `startDate` ·
 * pattern strictement identique à `useVehicleFullYearTax`. Cleanup
 * `onScopeDispose` · pas de fuite si parent détruit pendant debounce.
 *
 * Sémantique · les 3 champs `dailyCents`/`weeklyCents`/`monthlyCents`
 * peuvent être `null` si aucun tarif n'a été saisi pour cette année
 * (UI affiche un tiret muet `-`).
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
    /** Date ISO `YYYY-MM-DD` (de `form.start_date`). Vide = année courante. */
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
