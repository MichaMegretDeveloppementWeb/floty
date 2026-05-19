import type { InertiaForm } from '@inertiajs/vue3';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import { registryLookup as registryLookupRoute } from '@/routes/user/vehicles';

type VehicleRegistryLookupResult =
    App.Data.User.Vehicle.VehicleRegistryLookupResultData;

type FormFieldKey = keyof VehicleFormShape;

type ManualCheckField = {
    key: FormFieldKey;
    label: string;
    applicable?: (form: VehicleFormShape) => boolean;
};

export type RegistryLookupState = {
    loading: Ref<boolean>;
    lastFetchedAt: Ref<string | null>;
    errorMessage: Ref<string | null>;
    missingFields: ComputedRef<ManualCheckField[]>;
    lookup: (licensePlate: string) => Promise<void>;
};

const MANUAL_CHECK_FIELDS: ReadonlyArray<ManualCheckField> = [
    { key: 'brand', label: 'Marque' },
    { key: 'model', label: 'Modèle' },
    { key: 'vin', label: 'VIN' },
    { key: 'color', label: 'Couleur' },
    { key: 'first_origin_registration_date', label: '1ère immatriculation (origine)' },
    { key: 'first_french_registration_date', label: '1ère immatriculation France' },
    { key: 'acquisition_date', label: "Date d'acquisition" },
    { key: 'mileage_current', label: 'Kilométrage actuel' },
    { key: 'reception_category', label: 'Catégorie de réception' },
    { key: 'body_type', label: 'Carrosserie' },
    { key: 'seats_count', label: 'Nombre de places' },
    { key: 'energy_source', label: "Source d'énergie" },
    { key: 'underlying_combustion_engine_type', label: 'Type de moteur thermique sous-jacent' },
    { key: 'euro_standard', label: 'Norme Euro' },
    { key: 'homologation_method', label: "Méthode d'homologation" },
    { key: 'co2_wltp', label: 'CO₂ WLTP' },
    { key: 'co2_nedc', label: 'CO₂ NEDC' },
    { key: 'taxable_horsepower', label: 'Puissance fiscale' },
    { key: 'kerb_mass', label: 'Masse à vide' },
    { key: 'accepts_e85', label: "Acceptation du superéthanol E85" },
    { key: 'handicap_access', label: 'Accès handicapés' },
    {
        key: 'm1_special_use',
        label: 'Usage spécial M1',
        applicable: (form) => form.reception_category === 'M1',
    },
    {
        key: 'n1_passenger_transport',
        label: 'Transport de personnes (N1)',
        applicable: (form) => form.body_type === 'CTTE',
    },
    {
        key: 'n1_removable_second_row_seat',
        label: 'Banquette amovible (N1)',
        applicable: (form) => form.body_type === 'CTTE',
    },
    {
        key: 'n1_ski_lift_use',
        label: 'Usage remontée mécanique (N1)',
        applicable: (form) => form.body_type === 'BE',
    },
];

/**
 * Bind the registry lookup endpoint to a vehicle Inertia form.
 */
export function useVehicleRegistryLookup(
    form: InertiaForm<VehicleFormShape>,
): RegistryLookupState {
    const loading = ref<boolean>(false);
    const lastFetchedAt = ref<string | null>(null);
    const errorMessage = ref<string | null>(null);
    const apiProvidedFields = ref<Set<FormFieldKey>>(new Set());

    const missingFields = computed<ManualCheckField[]>(() => {
        if (lastFetchedAt.value === null) {
            return [];
        }

        return MANUAL_CHECK_FIELDS.filter((field) => {
            if (field.applicable && !field.applicable(form)) {
                return false;
            }
            if (apiProvidedFields.value.has(field.key)) {
                return false;
            }
            const value = form[field.key];
            if (typeof value === 'boolean') {
                return true;
            }
            return value === '' || value === null || value === undefined;
        });
    });

    /**
     * Trigger the lookup and hydrate the form from the response.
     */
    async function lookup(licensePlate: string): Promise<void> {
        if (loading.value) {
            return;
        }

        loading.value = true;
        errorMessage.value = null;

        try {
            const response = await fetch(registryLookupRoute.url(), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: JSON.stringify({ license_plate: licensePlate }),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                const code =
                    body !== null && typeof body === 'object' && 'error' in body
                        ? ((body as { error?: { code?: string } }).error?.code ??
                          null)
                        : null;
                errorMessage.value = mapErrorMessage(code);
                return;
            }

            const result = (await response.json()) as VehicleRegistryLookupResult;
            applyResultToForm(form, result, apiProvidedFields.value);
            lastFetchedAt.value = result.fetchedAt;
        } catch {
            errorMessage.value =
                'Erreur réseau. Vérifiez votre connexion et réessayez.';
        } finally {
            loading.value = false;
        }
    }

    return { loading, lastFetchedAt, errorMessage, missingFields, lookup };
}

/**
 * Read the XSRF cookie value, decoded for use in the header.
 */
function readXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

/**
 * Map a backend error code to a short French message displayed inline.
 */
function mapErrorMessage(code: string | null): string {
    switch (code) {
        case 'not_found':
            return 'Aucune donnée correspondante à cette immatriculation.';
        case 'timeout':
            return 'Le service tarde à répondre. Réessayez ou saisissez les informations manuellement.';
        case 'rate_limited':
            return 'Trop de requêtes envoyées. Patientez un instant avant de réessayer.';
        case 'unavailable':
            return 'Service indisponible. Saisissez les informations manuellement.';
        default:
            return 'Erreur lors de la récupération. Saisissez les informations manuellement.';
    }
}

/**
 * Hydrate the form from a successful lookup result and track which
 * fields the API actually provided.
 */
function applyResultToForm(
    form: InertiaForm<VehicleFormShape>,
    result: VehicleRegistryLookupResult,
    provided: Set<FormFieldKey>,
): void {
    provided.clear();

    form.license_plate = result.licensePlate;
    provided.add('license_plate');

    if (result.brand !== null) {
        form.brand = result.brand;
        provided.add('brand');
    }
    if (result.model !== null) {
        form.model = result.model;
        provided.add('model');
    }
    if (result.vin !== null) {
        form.vin = result.vin;
        provided.add('vin');
    }
    if (result.color !== null) {
        form.color = result.color;
        provided.add('color');
    }
    if (result.firstFrenchRegistrationDate !== null) {
        form.first_french_registration_date = result.firstFrenchRegistrationDate;
        provided.add('first_french_registration_date');
    }
    if (result.firstOriginRegistrationDate !== null) {
        form.first_origin_registration_date = result.firstOriginRegistrationDate;
        provided.add('first_origin_registration_date');
    }
    if (result.receptionCategory !== null) {
        form.reception_category = result.receptionCategory;
        form.vehicle_user_type = result.receptionCategory === 'M1' ? 'VP' : 'VU';
        provided.add('reception_category');
    }
    if (result.bodyType !== null) {
        form.body_type = result.bodyType;
        provided.add('body_type');
    }
    if (result.seatsCount !== null) {
        form.seats_count = result.seatsCount;
        provided.add('seats_count');
    }
    if (result.energySource !== null) {
        form.energy_source = result.energySource;
        provided.add('energy_source');
    }
    if (result.underlyingCombustionEngineType !== null) {
        form.underlying_combustion_engine_type =
            result.underlyingCombustionEngineType;
        provided.add('underlying_combustion_engine_type');
    }
    if (result.euroStandard !== null) {
        form.euro_standard = result.euroStandard;
        provided.add('euro_standard');
    }
    if (result.homologationMethod !== null) {
        form.homologation_method = result.homologationMethod;
        provided.add('homologation_method');
    }
    if (result.co2Wltp !== null) {
        form.co2_wltp = result.co2Wltp;
        provided.add('co2_wltp');
    }
    if (result.co2Nedc !== null) {
        form.co2_nedc = result.co2Nedc;
        provided.add('co2_nedc');
    }
    if (result.taxableHorsepower !== null) {
        form.taxable_horsepower = result.taxableHorsepower;
        provided.add('taxable_horsepower');
    }
    if (result.kerbMass !== null) {
        form.kerb_mass = result.kerbMass;
        provided.add('kerb_mass');
    }
    if (result.acceptsE85 !== null) {
        form.accepts_e85 = result.acceptsE85;
        provided.add('accepts_e85');
    }
    if (result.handicapAccess !== null) {
        form.handicap_access = result.handicapAccess;
        provided.add('handicap_access');
    }
    if (result.m1SpecialUse !== null) {
        form.m1_special_use = result.m1SpecialUse;
        provided.add('m1_special_use');
    }
    if (result.n1PassengerTransport !== null) {
        form.n1_passenger_transport = result.n1PassengerTransport;
        provided.add('n1_passenger_transport');
    }
    if (result.n1RemovableSecondRowSeat !== null) {
        form.n1_removable_second_row_seat = result.n1RemovableSecondRowSeat;
        provided.add('n1_removable_second_row_seat');
    }
    if (result.n1SkiLiftUse !== null) {
        form.n1_ski_lift_use = result.n1SkiLiftUse;
        provided.add('n1_ski_lift_use');
    }
}
