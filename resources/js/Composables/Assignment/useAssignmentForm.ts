import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';
import { storeBulk as storeBulkRoute } from '@/routes/user/planning/contracts';

/**
 * État et soumission du formulaire d'attribution rapide
 * (Pages/User/Assignments/Index.vue) ou du drawer planning
 * (Components/Features/Planning/WeekDrawer/AssignmentForm.vue).
 *
 * Le composable est instancié par le composant parent ; chaque
 * instance possède son propre état (pas de singleton).
 *
 * **Refonte 04.F (ADR-0014)** : crée désormais un **contrat** sur la
 * plage `[min(dates), max(dates)]` au lieu d'attributions journalières.
 * L'UX MultiDatePicker est conservée temporairement ; la refonte vers
 * un `DateRangePicker` (sélection 2 clics) est la cible UI cible.
 */
export type UseAssignmentFormReturn = {
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    dates: Ref<string[]>;
    submitting: Ref<boolean>;
    canSubmit: ComputedRef<boolean>;
    /**
     * POST le contrat sur la plage [min(dates), max(dates)]. Retourne
     * `true` si succès, `false` sinon (toast erreur déjà affiché).
     */
    submit: () => Promise<boolean>;
    reset: () => void;
};

export function useAssignmentForm(): UseAssignmentFormReturn {
    const api = useApi();
    const toasts = useToasts();

    const vehicleId = ref<number | null>(null);
    const companyId = ref<number | null>(null);
    const dates = ref<string[]>([]);
    const submitting = ref(false);

    const canSubmit = computed<boolean>(
        () =>
            vehicleId.value !== null &&
            companyId.value !== null &&
            dates.value.length > 0 &&
            !submitting.value,
    );

    const reset = (): void => {
        vehicleId.value = null;
        companyId.value = null;
        dates.value = [];
        submitting.value = false;
    };

    const submit = async (): Promise<boolean> => {
        if (!canSubmit.value) {
            return false;
        }

        submitting.value = true;

        try {
            const sorted = [...dates.value].sort();
            // canSubmit garantit dates.length > 0 ; assertions explicites
            // pour le typeur (TS ne propage pas la garantie via canSubmit).
            const startDate = sorted[0] as string;
            const endDate = sorted[sorted.length - 1] as string;

            const payload: App.Data.User.Contract.BulkStoreContractsData = {
                vehicleIds: [vehicleId.value as number],
                companyId: companyId.value as number,
                driverId: null,
                startDate,
                endDate,
                contractReference: null,
                contractType: 'lcd',
                notes: null,
            };

            const result = await api.post<{ createdIds: number[] }>(
                storeBulkRoute.url(),
                payload,
            );

            const created = result.createdIds.length;
            toasts.push({
                tone: 'success',
                title: 'Contrat enregistré',
                description: `${created} contrat${created > 1 ? 's' : ''} créé${created > 1 ? 's' : ''} (${startDate} → ${endDate}).`,
            });

            return true;
        } catch {
            return false;
        } finally {
            submitting.value = false;
        }
    };

    return {
        vehicleId,
        companyId,
        dates,
        submitting,
        canSubmit,
        submit,
        reset,
    };
}
