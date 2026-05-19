import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { CompanyFormShape } from '@/pages/User/Companies/Create/forms';
import { update as companiesUpdateRoute } from '@/routes/user/companies';

type CompanyEdit = App.Data.User.Company.CompanyEditData;

/**
 * Inertia form for the company edit page (PATCH).
 *
 * Initial values are pre-filled from the Company DTO. Shape reuses
 * `CompanyFormShape` (snake_case for Spatie Data auto-mapping on the backend),
 * minus `is_active` which is not driven by this form.
 */
export function useCompanyEditForm(company: CompanyEdit): {
    form: InertiaForm<CompanyFormShape>;
    submit: () => void;
} {
    const form = useForm<CompanyFormShape>({
        legal_name: company.legalName,
        color: company.color,
        siren: company.siren ?? '',
        siret: company.siret ?? '',
        address_line_1: company.addressLine1 ?? '',
        address_line_2: company.addressLine2 ?? '',
        postal_code: company.postalCode ?? '',
        city: company.city ?? '',
        country: company.country,
        contact_name: company.contactName ?? '',
        contact_email: company.contactEmail ?? '',
        contact_phone: company.contactPhone ?? '',
    });

    const submit = (): void => {
        form.patch(companiesUpdateRoute.url({ company: company.id }));
    };

    return { form, submit };
}
