import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { previewTaxes as previewTaxesRoute } from '@/routes/user/planning';

/**
 * Standalone fiscal preview for a contract assignment.
 *
 * Debounced (200 ms) state backed by `POST /app/planning/preview-taxes`.
 * Timer is cleaned up via `onScopeDispose` so no leak occurs when the parent unmounts mid-debounce.
 *
 * No separate `error` output: `useApi` pushes an error toast automatically; we just set `preview = null`.
 *
 * LCD/LLD is computed per individual contract (no annual cumulation),
 * so the preview answers strictly to the standalone fiscal cost.
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
     * Triggers a debounced fetch. Call from a `watch` on the (vehicleId / companyId / dates) refs.
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
