/**
 * Shape des champs purement fiscaux (VFC) — partagé entre le form
 * véhicule complet (Create/Edit) et le form d'édition d'une VFC
 * isolée depuis la modale Historique. Permet de typer
 * `FiscalCharacteristicsSection` de façon générique sur tout
 * formulaire qui contient ces champs.
 *
 * `pollutant_category` n'est PAS un input — il est dérivé côté front
 * par `derivePollutantCategory()` et persisté côté backend par le
 * Repository. Ne pas le rajouter dans le shape.
 */
export type FiscalCharacteristicsFieldsShape = {
    reception_category: string;
    vehicle_user_type: string;
    body_type: string;
    seats_count: number;
    energy_source: App.Enums.Vehicle.EnergySource;
    underlying_combustion_engine_type: App.Enums.Vehicle.UnderlyingCombustionEngineType | '';
    euro_standard: App.Enums.Vehicle.EuroStandard | '';
    homologation_method: string;
    co2_wltp: number | null;
    co2_nedc: number | null;
    taxable_horsepower: number | null;
    // Spécificités fiscales (toujours visibles)
    kerb_mass: number | null;
    handicap_access: boolean;
    // Usage spécifique (conditionnels selon catégorie/carrosserie)
    m1_special_use: boolean;
    n1_passenger_transport: boolean;
    n1_removable_second_row_seat: boolean;
    n1_ski_lift_use: boolean;
};

/**
 * Shape du formulaire de création/édition d'un véhicule (snake_case
 * pour matcher la validation backend Spatie Data après auto-mapping).
 *
 * Réutilisé par les partials sectionnés du formulaire pour typer
 * l'objet `useForm()` reçu en prop.
 */
export type VehicleFormShape = FiscalCharacteristicsFieldsShape & {
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
};
