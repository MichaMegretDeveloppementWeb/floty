import { useForm } from '@inertiajs/vue3';
import { store as storeRoute, update as updateRoute } from '@/routes/user/drivers';

type CompanyOption = { id: number; shortCode: string; legalName: string };

export type CreateFormShape = {
    first_name: string;
    last_name: string;
    initial_company_id: number | null;
    initial_joined_at: string;
};

export type EditFormShape = {
    first_name: string;
    last_name: string;
};

export function useCreateDriverForm(initial?: { companyId?: number }) {
    const form = useForm<CreateFormShape>({
        first_name: '',
        last_name: '',
        initial_company_id: initial?.companyId ?? null,
        initial_joined_at: new Date().toISOString().slice(0, 10),
    });

    function submit(): void {
        form.post(storeRoute().url, {
            preserveScroll: true,
        });
    }

    return { form, submit };
}

export function useEditDriverForm(driver: { id: number; firstName: string; lastName: string }) {
    const form = useForm<EditFormShape>({
        first_name: driver.firstName,
        last_name: driver.lastName,
    });

    function submit(): void {
        form.patch(updateRoute(driver.id).url, {
            preserveScroll: true,
        });
    }

    return { form, submit };
}

export type { CompanyOption };
