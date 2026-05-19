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
    driver_ids: number[];
    start_date: string;
    end_date: string;
    contract_reference: string | null;
    notes: string | null;
};

/**
 * State and submission for the Contract Create/Edit form.
 *
 * `create` mode: POST `/app/contracts` (empty defaults).
 * `edit`   mode: PATCH `/app/contracts/{id}` (pre-filled from DTO).
 *
 * State is an Inertia `useForm()` with automatic server-side validation error handling (422)
 * via `form.errors.<field>`.
 *
 * `contract_type` is not part of the form: it is derived backend-side from [start_date, end_date]
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
        driver_ids: contract?.drivers.map((d) => d.id) ?? [],
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
