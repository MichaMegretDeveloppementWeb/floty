import { computed, watch } from 'vue';
import type { Ref } from 'vue';
import { useRentalPreview } from '@/Composables/Billing/useRentalPreview';

/**
 * Rental preview for the Contract form (Create/Edit page + planning drawer).
 *
 * Thin wrapper around `useRentalPreview` accepting the form's native refs
 * (vehicleId / companyId / startDate / endDate) and deriving the date list
 * expected by `POST /app/planning/preview-rentals`.
 *
 * Watches the 4 refs and fires a debounced fetch (200 ms, inherited from `useRentalPreview`).
 * If any of the 4 fields is missing the preview is set to `null`.
 *
 * Semantics strictly equivalent to the final invoice (civil-month split + `OptimalRateBreakdown`
 * + applied discounts); non-fiscal counterpart of `useContractFiscalPreview`.
 */
export type UseContractRentalPreviewReturn = {
    preview: Ref<App.Data.User.Planning.RentalPreviewData | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

export function useContractRentalPreview(opts: {
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    startDate: Ref<string>;
    endDate: Ref<string>;
}): UseContractRentalPreviewReturn {
    const { preview, loading, fetch, reset } = useRentalPreview();

    // Backend takes min/max of dates to rebuild the synthetic contract range.
    // No need to expand every day client-side (avoids timezone bugs around DST).
    const dates = computed<string[]>(() => {
        const start = opts.startDate.value;
        const end = opts.endDate.value;

        if (!start || !end || start > end) {
            return [];
        }

        return start === end ? [start] : [start, end];
    });

    watch(
        () => [opts.vehicleId.value, opts.companyId.value, dates.value] as const,
        ([vehicleId, companyId, dateList]) => {
            fetch({
                vehicleId,
                companyId,
                dates: dateList,
            });
        },
        { immediate: true, deep: true },
    );

    return { preview, loading, reset };
}
