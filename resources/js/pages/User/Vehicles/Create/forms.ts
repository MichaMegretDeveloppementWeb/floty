/**
 * Shape of the fiscal (VFC) fields, shared between the full vehicle
 * form (Create/Edit) and the standalone VFC edit modal from the
 * vehicle history. `pollutant_category` is not an input · it is
 * derived on the frontend by `derivePollutantCategory()` and persisted
 * on the backend by the repository.
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
    // E85 flag (L. 421-125 abatement, 2025+) · derived from the 9 P.3
    // codes of the registration certificate {FE, FG, FN, FL, FH, FR,
    // FQ, FM, FP} (BOFiP BOI-AIS-MOB-10-20-40-20250604 § 160).
    accepts_e85: boolean;
    // Fiscal specifics (always visible).
    kerb_mass: number | null;
    handicap_access: boolean;
    // Specific use (conditional on category/body).
    m1_special_use: boolean;
    n1_passenger_transport: boolean;
    n1_removable_second_row_seat: boolean;
    n1_ski_lift_use: boolean;
};

/**
 * Shape of the vehicle create/edit form (snake_case to match the
 * backend Spatie Data validation after auto-mapping). Re-used by the
 * sectioned partials of the form to type the injected `useForm()` prop.
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
