import { computed, watch } from 'vue';
import type { Ref } from 'vue';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';

/**
 * Fiscal preview for the Contract form (Create/Edit page + planning drawer).
 *
 * Thin wrapper around `useFiscalPreview` accepting the form's native refs
 * (vehicleId / companyId / startDate / endDate) and deriving the date list
 * expected by `POST /app/planning/preview-taxes`.
 *
 * Watches the 4 refs and fires a debounced fetch (200 ms, inherited from `useFiscalPreview`)
 * on each change. If any of the 4 fields is missing the preview is set to `null`.
 *
 * Semantics: standalone contract computation, no annual cumulation; the contract duration alone
 * determines LCD vs LLD.
 */
export type UseContractFiscalPreviewReturn = {
    preview: Ref<App.Data.User.Fiscal.FiscalPreviewData | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

export function useContractFiscalPreview(opts: {
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    startDate: Ref<string>;
    endDate: Ref<string>;
}): UseContractFiscalPreviewReturn {
    const { preview, loading, fetch, reset } = useFiscalPreview();

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
