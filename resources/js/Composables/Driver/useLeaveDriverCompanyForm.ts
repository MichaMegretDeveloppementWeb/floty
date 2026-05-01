import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Ref } from 'vue';
import { leave as leaveRoute } from '@/routes/user/drivers/memberships';

type FutureContract = {
    id: number;
    vehicleLicensePlate: string;
    startDate: string;
    endDate: string;
};

type FormShape = {
    left_at: string;
    future_contracts_resolution: 'replace' | 'detach' | 'none';
    replacement_map: Record<number, number | null>;
};

/**
 * Workflow Q6 — modale de sortie d'un driver d'une entreprise.
 *
 * État géré :
 * - `leftAt` (date de sortie)
 * - mode résolution (`replace` / `detach` / `none` calculé selon contrats)
 * - `replacementMap` (clé contractId → driverId de remplacement, ou null)
 *
 * Le composant parent (LeaveDriverCompanyModal) précharge les contrats à
 * venir détectés et les passe via `futureContracts`.
 */
export function useLeaveDriverCompanyForm(opts: {
    driverId: number;
    companyId: number;
    futureContracts: Ref<readonly FutureContract[]>;
}) {
    const form = useForm<FormShape>({
        left_at: new Date().toISOString().slice(0, 10),
        future_contracts_resolution: 'none',
        replacement_map: {},
    });

    const hasFutureContracts = computed<boolean>(
        () => opts.futureContracts.value.length > 0,
    );

    const mode = ref<'replace' | 'detach' | 'none'>('none');

    function setMode(value: 'replace' | 'detach' | 'none'): void {
        mode.value = value;
        form.future_contracts_resolution = value;

        if (value === 'replace') {
            const map: Record<number, number | null> = {};

            for (const c of opts.futureContracts.value) {
                map[c.id] = null;
            }

            form.replacement_map = map;
        } else {
            form.replacement_map = {};
        }
    }

    function setReplacement(contractId: number, driverId: number | null): void {
        form.replacement_map = {
            ...form.replacement_map,
            [contractId]: driverId,
        };
    }

    function submit(onSuccess?: () => void): void {
        form.transform((data) => ({
            ...data,
            replacement_map:
                data.future_contracts_resolution === 'replace'
                    ? data.replacement_map
                    : {},
        }));

        form.patch(leaveRoute([opts.driverId, opts.companyId]).url, {
            preserveScroll: true,
            onSuccess: () => {
                onSuccess?.();
            },
        });
    }

    return {
        form,
        hasFutureContracts,
        mode,
        setMode,
        setReplacement,
        submit,
    };
}

export type { FutureContract };
