import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import {
    store as contractsStoreRoute,
    update as contractsUpdateRoute,
} from '@/routes/user/contracts';

type ContractFormShape = {
    vehicle_id: number | null;
    company_id: number | null;
    driver_id: number | null;
    start_date: string;
    end_date: string;
    contract_reference: string | null;
    notes: string | null;
};

/**
 * État + soumission du formulaire Create/Edit Contract.
 *
 * Mode `create` : POST `/app/contracts` (vide par défaut).
 * Mode `edit`   : PATCH `/app/contracts/{id}` (pré-rempli depuis le DTO).
 *
 * Le state est un Inertia `useForm()` — gestion automatique des erreurs
 * de validation (422) côté serveur via `form.errors.<field>`.
 *
 * **Refonte 04.K** : `contract_type` n'est plus dans le formulaire — il
 * est dérivé automatiquement côté backend depuis [start_date, end_date]
 * via {@see Contract::deriveTypeFromDates()}.
 */
export function useContractForm(
    contract?: App.Data.User.Contract.ContractData,
): {
    form: ReturnType<typeof useForm<ContractFormShape>>;
    isEdit: boolean;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const isEdit = contract !== undefined;

    const initial: ContractFormShape = {
        vehicle_id: contract?.vehicleId ?? null,
        company_id: contract?.companyId ?? null,
        driver_id: contract?.driverId ?? null,
        start_date: contract?.startDate ?? '',
        end_date: contract?.endDate ?? '',
        contract_reference: contract?.contractReference ?? null,
        notes: contract?.notes ?? null,
    };

    const form = useForm<ContractFormShape>(initial);

    const canSubmit = computed<boolean>(
        () =>
            form.vehicle_id !== null &&
            form.company_id !== null &&
            form.start_date !== '' &&
            form.end_date !== '' &&
            !form.processing,
    );

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        if (isEdit) {
            form.patch(contractsUpdateRoute.url({ contract: contract.id }), {
                preserveScroll: true,
            });
        } else {
            form.post(contractsStoreRoute.url(), {
                preserveScroll: true,
            });
        }
    };

    return { form, isEdit, canSubmit, submit };
}
