import { computed, onScopeDispose, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { fullYearTax as fullYearTaxRoute } from '@/routes/user/vehicles';

/**
 * Calcul A · taxe pleine année d'un véhicule pour l'année dérivée de
 * `startDate` (fallback année courante). Déclenché à la volée par le
 * form Create/Edit Contract quand l'utilisateur sélectionne un
 * véhicule ou change la date de début. Affiché en indication discrète
 * sous le sélecteur véhicule.
 *
 * Backend · `GET /app/vehicles/{vehicle}/full-year-tax?year=YYYY`
 * Service · {@see VehicleListingService::fullYearTaxForVehicle}
 *
 * Watch reactif debouncé (200 ms) sur `vehicleId` + `startDate` ·
 * un seul appel par vraie sélection utilisateur, pas un appel par
 * frappe. Cleanup `onScopeDispose` · pas de fuite si le composant
 * parent est détruit pendant un debounce.
 *
 * Sémantique du résultat ·
 *   - `cents` · taxe pleine année en centimes (entier)
 *   - `year` · année effectivement calculée (peut différer de la
 *     demandée si fallback sur année connue voisine)
 *   - `fallback` · true ssi `year` ≠ `requestedYear`
 *
 * Audit perf 2026-05-16 S2.5 · remplace le pré-calcul lourd de
 * `VehicleOptionData::fullYearTaxByYear` (192 pipeline runs gaspillés
 * au mount Create/Edit) par un seul calcul à la sélection véhicule.
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
    /** Date ISO `YYYY-MM-DD` (de `form.start_date`). Vide = année courante. */
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
