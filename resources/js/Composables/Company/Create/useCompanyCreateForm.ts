import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { CompanyFormShape } from '@/pages/User/Companies/Create/forms';
import { store as companiesStoreRoute } from '@/routes/user/companies';

/**
 * Form Inertia + valeurs initiales + soumission de la page
 * « Nouvelle entreprise ».
 */
export function useCompanyCreateForm(): {
    form: InertiaForm<CompanyFormShape>;
    submit: () => void;
} {
    const form = useForm<CompanyFormShape>({
        legal_name: '',
        color: 'indigo',
        siren: '',
        siret: '',
        address_line_1: '',
        address_line_2: '',
        postal_code: '',
        city: '',
        country: 'FR',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
    });

    const submit = (): void => {
        form.post(companiesStoreRoute.url());
    };

    return { form, submit };
}
