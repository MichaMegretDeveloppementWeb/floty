import { computed, watch } from 'vue';
import type { Ref } from 'vue';
import { useRentalPreview } from '@/Composables/Billing/useRentalPreview';

/**
 * Aperçu loyer pour le formulaire Contract (page Create/Edit + drawer
 * planning, SC4 · 2026-05-18). Wrapper autour de `useRentalPreview` qui
 * accepte les refs natives du formulaire (vehicleId / companyId /
 * startDate / endDate) et dérive automatiquement la liste de dates
 * attendue par l'endpoint `POST /app/planning/preview-rentals`.
 *
 * Le composable surveille les 4 refs · à chaque changement, déclenche
 * un fetch debouncé (200 ms hérités de `useRentalPreview`). Si un des
 * 4 champs manque, le preview est mis à `null`.
 *
 * Sémantique strictement équivalente à la facture finale (split par
 * mois civil + `OptimalRateBreakdown` + réductions appliquées) ·
 * pendant non-fiscal de `useContractFiscalPreview`.
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

    // Le backend prend min/max des dates pour reconstruire la plage du
    // contrat synthétique · pas besoin d'expandre tous les jours côté
    // front (et ça évite les bugs de timezone sur les transitions DST).
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
