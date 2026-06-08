import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import {
    store as storeRoute,
    update as updateRoute,
} from '@/routes/user/drivers';

type CompanyOption = { id: number; shortCode: string; legalName: string };

export type CreateFormShape = {
    first_name: string;
    last_name: string;
    email: string;
    initial_company_id: number | null;
    initial_joined_at: string;
};

export type EditFormShape = {
    first_name: string;
    last_name: string;
    email: string;
};

/**
 * The email field is optional: an empty (or whitespace) input is sent as
 * `null` so the backend's `nullable|email` rule treats "no email" as absent
 * rather than failing the format check on an empty string.
 */
function normalizeEmail(email: string): string | null {
    const trimmed = email.trim();

    return trimmed === '' ? null : trimmed;
}

export type UseCreateDriverFormReturn = {
    form: InertiaForm<CreateFormShape>;
    submit: () => void;
};

export type UseEditDriverFormReturn = {
    form: InertiaForm<EditFormShape>;
    submit: () => void;
};

export function useCreateDriverForm(initial?: { companyId?: number }): UseCreateDriverFormReturn {
    const form = useForm<CreateFormShape>({
        first_name: '',
        last_name: '',
        email: '',
        initial_company_id: initial?.companyId ?? null,
        initial_joined_at: new Date().toISOString().slice(0, 10),
    });

    function submit(): void {
        form
            .transform((data) => ({
                ...data,
                email: normalizeEmail(data.email),
            }))
            .post(storeRoute.url(), {
                preserveScroll: true,
            });
    }

    return { form, submit };
}

export function useEditDriverForm(driver: {
    id: number;
    firstName: string;
    lastName: string;
    email?: string | null;
}): UseEditDriverFormReturn {
    const form = useForm<EditFormShape>({
        first_name: driver.firstName,
        last_name: driver.lastName,
        email: driver.email ?? '',
    });

    function submit(): void {
        form
            .transform((data) => ({
                ...data,
                email: normalizeEmail(data.email),
            }))
            .patch(updateRoute.url(driver.id), {
                preserveScroll: true,
            });
    }

    return { form, submit };
}

export type { CompanyOption };
