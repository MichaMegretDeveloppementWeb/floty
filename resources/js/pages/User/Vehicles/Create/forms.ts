/**
 * Shape du formulaire de création/édition d'un véhicule (snake_case
 * pour matcher la validation backend Spatie Data après auto-mapping).
 *
 * Réutilisé par les partials sectionnés du formulaire pour typer
 * l'objet `useForm()` reçu en prop.
 */
export type VehicleFormShape = {
    license_plate: string;
    brand: string;
    model: string;
    vin: string;
    color: string;
    first_french_registration_date: string;
    first_origin_registration_date: string;
    first_economic_use_date: string;
    acquisition_date: string;
    mileage_current: number | null;
    notes: string;
    reception_category: string;
    vehicle_user_type: string;
    body_type: string;
    seats_count: number;
    energy_source: string;
    euro_standard: string;
    pollutant_category: string;
    homologation_method: string;
    co2_wltp: number | null;
    co2_nedc: number | null;
    taxable_horsepower: number | null;
};
