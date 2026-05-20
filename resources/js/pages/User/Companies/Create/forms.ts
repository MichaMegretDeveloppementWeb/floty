/**
 * Shape of the company create/edit form (snake_case to match the
 * backend Spatie Data validation after auto-mapping via the
 * SnakeCaseMapper). Re-used by the sectioned partials of the form to
 * type the injected `useForm()` prop.
 */
export type CompanyFormShape = {
    legal_name: string;
    color: string;
    siren: string;
    siret: string;
    address_line_1: string;
    address_line_2: string;
    postal_code: string;
    city: string;
    country: string;
    contact_name: string;
    contact_email: string;
    contact_phone: string;
};
