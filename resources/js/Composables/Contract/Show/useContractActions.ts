import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import {
    destroy as contractsDestroyRoute,
    edit as contractsEditRoute,
} from '@/routes/user/contracts';

/**
 * Actions Edit / Delete pour la page Show d'un contrat.
 *
 * Le delete utilise une confirmation modale via une ref `confirmOpen`.
 * L'appelant doit afficher la modale et appeler `confirmDelete()` pour
 * déclencher la requête. `submitting` reste `true` durant l'appel.
 */
export function useContractActions(contractId: number): {
    confirmOpen: Ref<boolean>;
    submitting: Ref<boolean>;
    goEdit: () => void;
    requestDelete: () => void;
    cancelDelete: () => void;
    confirmDelete: () => void;
} {
    const confirmOpen = ref(false);
    const submitting = ref(false);

    const goEdit = (): void => {
        router.visit(contractsEditRoute.url({ contract: contractId }));
    };

    const requestDelete = (): void => {
        confirmOpen.value = true;
    };

    const cancelDelete = (): void => {
        confirmOpen.value = false;
    };

    const confirmDelete = (): void => {
        submitting.value = true;
        router.delete(contractsDestroyRoute.url({ contract: contractId }), {
            preserveScroll: false,
            onFinish: () => {
                submitting.value = false;
                confirmOpen.value = false;
            },
        });
    };

    return {
        confirmOpen,
        submitting,
        goEdit,
        requestDelete,
        cancelDelete,
        confirmDelete,
    };
}
