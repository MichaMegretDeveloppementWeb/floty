import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { previewRentals as previewRentalsRoute } from '@/routes/user/planning';

/**
 * Aperçu **loyer standalone** d'une attribution (location/contrat) ·
 * pendant non-fiscal de {@link useFiscalPreview} (SC4 · 2026-05-18).
 *
 * État debounced (200 ms) consommant `POST /app/planning/preview-rentals`.
 * Le composable gère son propre timer avec cleanup via `onScopeDispose` ·
 * pas de fuite si le composant parent est détruit pendant un debounce.
 *
 * Sémantique : strictement équivalent à la facture finale · split par
 * mois civil, `OptimalRateBreakdown` par mois, réductions appliquées
 * via `DiscountApplier`. Le total net retourné est celui qui apparaîtra
 * sur la facture mensuelle effective si ce contrat est créé.
 */
export type RentalPreviewInput = {
    vehicleId: number | null;
    companyId: number | null;
    dates: string[];
};

export type UseRentalPreviewReturn = {
    preview: Ref<App.Data.User.Planning.RentalPreviewData | null>;
    loading: Ref<boolean>;
    fetch: (input: RentalPreviewInput) => void;
    reset: () => void;
};

const DEBOUNCE_MS = 200;

export function useRentalPreview(): UseRentalPreviewReturn {
    const api = useApi();
    const preview = ref<App.Data.User.Planning.RentalPreviewData | null>(null);
    const loading = ref(false);
    let debounceHandle: number | null = null;

    const reset = (): void => {
        if (debounceHandle !== null) {
            window.clearTimeout(debounceHandle);
            debounceHandle = null;
        }

        preview.value = null;
        loading.value = false;
    };

    const fetch = (input: RentalPreviewInput): void => {
        if (debounceHandle !== null) {
            window.clearTimeout(debounceHandle);
        }

        if (
            input.vehicleId === null ||
            input.companyId === null ||
            input.dates.length === 0
        ) {
            preview.value = null;

            return;
        }

        debounceHandle = window.setTimeout(async () => {
            loading.value = true;

            try {
                preview.value = await api.post<App.Data.User.Planning.RentalPreviewData>(
                    previewRentalsRoute.url(),
                    {
                        vehicleId: input.vehicleId,
                        companyId: input.companyId,
                        dates: input.dates,
                    },
                );
            } catch {
                preview.value = null;
            } finally {
                loading.value = false;
            }
        }, DEBOUNCE_MS);
    };

    onScopeDispose(reset);

    return { preview, loading, fetch, reset };
}
