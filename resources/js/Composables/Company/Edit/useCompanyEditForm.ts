import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { CompanyFormShape } from '@/pages/User/Companies/Create/forms';
import { update as companiesUpdateRoute } from '@/routes/user/companies';

type CompanyDetail = App.Data.User.Company.CompanyDetailData;

/**
 * Form Inertia + valeurs initiales (pré-remplies depuis le DTO Company)
 * + soumission `PATCH` de la page « Modifier l'entreprise ». La shape
 * du form reprend `CompanyFormShape` (snake_case pour l'auto-mapping
 * Spatie Data côté backend) en ignorant `is_active` qui n'est pas
 * piloté par le formulaire d'édition.
 */
export function useCompanyEditForm(company: CompanyDetail): {
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
