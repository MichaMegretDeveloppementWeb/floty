import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useToasts } from '@/Composables/Shared/useToasts';
import { storeBulk as storeBulkRoute } from '@/routes/user/planning/assignments';

/**
 * État et soumission du formulaire d'attribution rapide
 * (Pages/User/Assignments/Index.vue) ou du drawer planning
 * (Components/Features/Planning/WeekDrawer/AssignmentForm.vue).
 *
 * Le composable est instancié par le composant parent ; chaque
 * instance possède son propre état (pas de singleton).
 */
export type UseAssignmentFormReturn = {
    vehicleId: Ref<number | null>;
    companyId: Ref<number | null>;
    dates: Ref<string[]>;
    submitting: Ref<boolean>;
    canSubmit: ComputedRef<boolean>;
    /**
     * POST l'attribution en masse. Retourne `true` si succès, `false`
     * sinon (toast erreur déjà affiché par useApi).
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
            const result = await api.post<App.Data.User.Assignment.BulkCreateResultData>(
                storeBulkRoute.url(),
                {
                    vehicleId: vehicleId.value,
                    companyId: companyId.value,
                    dates: dates.value,
                },
            );
            toasts.push({
                tone: 'success',
                title: 'Attribution enregistrée',
                description: `${result.inserted} jour${result.inserted > 1 ? 's' : ''} créé${result.inserted > 1 ? 's' : ''}${
                    result.skipped > 0
                        ? `, ${result.skipped} doublon${result.skipped > 1 ? 's' : ''} ignoré${result.skipped > 1 ? 's' : ''}`
                        : ''
                }.`,
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
