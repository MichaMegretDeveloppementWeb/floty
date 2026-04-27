import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { previewTaxes as previewTaxesRoute } from '@/routes/user/planning';

/**
 * Aperçu fiscal en temps réel pour la création d'une attribution.
 *
 * État debounced (200 ms) consommant
 * `POST /app/planning/preview-taxes`. Le composable gère son propre
 * timer avec cleanup via `onScopeDispose` — pas de fuite si le
 * composant parent est détruit pendant un debounce.
 *
 * Pas de retour `error` séparé : `useApi` push automatiquement un
 * toast erreur ; côté composable on remet juste `preview = null`.
 */
export type FiscalPreviewInput = {
    vehicleId: number | null;
    companyId: number | null;
    dates: string[];
};

export type UseFiscalPreviewReturn = {
    preview: Ref<App.Data.User.Fiscal.FiscalPreviewData | null>;
    loading: Ref<boolean>;
    /**
     * Déclenche un fetch debouncé. À appeler depuis un `watch` sur
     * les refs (vehicleId / companyId / dates) côté consommateur.
     */
    fetch: (input: FiscalPreviewInput) => void;
    reset: () => void;
};

const DEBOUNCE_MS = 200;

export function useFiscalPreview(): UseFiscalPreviewReturn {
    const api = useApi();
    const preview = ref<App.Data.User.Fiscal.FiscalPreviewData | null>(null);
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

    const fetch = (input: FiscalPreviewInput): void => {
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
                preview.value = await api.post<App.Data.User.Fiscal.FiscalPreviewData>(
                    previewTaxesRoute.url(),
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
