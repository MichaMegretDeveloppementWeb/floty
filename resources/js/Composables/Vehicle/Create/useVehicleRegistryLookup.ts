import type { InertiaForm } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import { registryLookup as registryLookupRoute } from '@/routes/user/vehicles';

type VehicleRegistryLookupResult =
    App.Data.User.Vehicle.VehicleRegistryLookupResultData;

export type RegistryLookupState = {
    loading: Ref<boolean>;
    lastFetchedAt: Ref<string | null>;
    lastError: Ref<string | null>;
    lookup: (licensePlate: string) => Promise<void>;
};

/**
 * Bind the registry lookup endpoint to a vehicle Inertia form.
 */
export function useVehicleRegistryLookup(
    form: InertiaForm<VehicleFormShape>,
): RegistryLookupState {
    const api = useApi();
    const loading = ref<boolean>(false);
    const lastFetchedAt = ref<string | null>(null);
    const lastError = ref<string | null>(null);

    /**
     * Trigger the lookup and hydrate the form from the response.
     */
    async function lookup(licensePlate: string): Promise<void> {
        if (loading.value) {
            return;
        }

        loading.value = true;
        lastError.value = null;

        try {
            const result = await api.post<VehicleRegistryLookupResult>(
                registryLookupRoute.url(),
                { license_plate: licensePlate },
            );

            applyResultToForm(form, result);
            lastFetchedAt.value = result.fetchedAt;
        } catch (error) {
            lastError.value =
                error instanceof Error ? error.message : 'lookup failed';
        } finally {
            loading.value = false;
        }
    }

    return { loading, lastFetchedAt, lastError, lookup };
}

/**
 * Copy non-null fields from the lookup result into the vehicle form.
 */
function applyResultToForm(
    form: InertiaForm<VehicleFormShape>,
    result: VehicleRegistryLookupResult,
): void {
    form.license_plate = result.licensePlate;

    if (result.brand !== null) {
        form.brand = result.brand;
    }
    if (result.model !== null) {
        form.model = result.model;
    }
    if (result.vin !== null) {
        form.vin = result.vin;
    }
    if (result.color !== null) {
        form.color = result.color;
    }
    if (result.firstFrenchRegistrationDate !== null) {
        form.first_french_registration_date =
            result.firstFrenchRegistrationDate;
    }
    if (result.firstOriginRegistrationDate !== null) {
        form.first_origin_registration_date =
            result.firstOriginRegistrationDate;
    }
    if (result.receptionCategory !== null) {
        form.reception_category = result.receptionCategory;
        form.vehicle_user_type =
            result.receptionCategory === 'M1' ? 'VP' : 'VU';
    }
    if (result.bodyType !== null) {
        form.body_type = result.bodyType;
    }
    if (result.seatsCount !== null) {
        form.seats_count = result.seatsCount;
    }
    if (result.energySource !== null) {
        form.energy_source = result.energySource;
    }
    if (result.underlyingCombustionEngineType !== null) {
        form.underlying_combustion_engine_type =
            result.underlyingCombustionEngineType;
    }
    if (result.euroStandard !== null) {
        form.euro_standard = result.euroStandard;
    }
    if (result.homologationMethod !== null) {
        form.homologation_method = result.homologationMethod;
    }
    if (result.co2Wltp !== null) {
        form.co2_wltp = result.co2Wltp;
    }
    if (result.co2Nedc !== null) {
        form.co2_nedc = result.co2Nedc;
    }
    if (result.taxableHorsepower !== null) {
        form.taxable_horsepower = result.taxableHorsepower;
    }
    if (result.kerbMass !== null) {
        form.kerb_mass = result.kerbMass;
    }
}
