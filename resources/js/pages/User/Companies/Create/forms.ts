/**
 * Shape du formulaire de création/édition d'entreprise (snake_case
 * pour matcher la validation backend Spatie Data après auto-mapping
 * via SnakeCaseMapper).
 *
 * Réutilisé par les partials sectionnés du formulaire pour typer
 * l'objet `useForm()` reçu en prop.
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
