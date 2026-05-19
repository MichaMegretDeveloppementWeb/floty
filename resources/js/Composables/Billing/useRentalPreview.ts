import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { previewRentals as previewRentalsRoute } from '@/routes/user/planning';

/**
 * Standalone rental preview for a contract assignment (non-fiscal counterpart of {@link useFiscalPreview}).
 *
 * Debounced (200 ms) state backed by `POST /app/planning/preview-rentals`.
 * Timer is cleaned up via `onScopeDispose` to avoid leaks when the parent unmounts mid-debounce.
 *
 * Semantics strictly mirror the final invoice: civil-month split,
 * `OptimalRateBreakdown` per month, discounts applied via `DiscountApplier`.
 * The returned net total matches what would appear on the actual monthly invoice.
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
