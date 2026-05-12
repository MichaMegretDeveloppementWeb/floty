import { computed, watch } from 'vue';
import type { Ref } from 'vue';
import { useFiscalPreview } from '@/Composables/Fiscal/useFiscalPreview';

/**
 * Aperçu fiscal pour le formulaire Contract (page Create/Edit + drawer
 * planning, chantier UX-Loc). Wrapper autour de `useFiscalPreview` qui
 * accepte les refs natives du formulaire (vehicleId / companyId /
 * startDate / endDate) et dérive automatiquement la liste de dates
 * attendue par l'endpoint `POST /app/planning/preview-taxes`.
 *
 * Le composable surveille les 4 refs : à chaque changement, déclenche
 * un fetch debouncé (200 ms hérités de `useFiscalPreview`). Si un des
 * 4 champs manque, le preview est mis à `null`.
 *
 * Sémantique : calcul standalone du contrat, pas de cumul annuel ·
 * la durée du contrat seul détermine LCD vs LLD.
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
