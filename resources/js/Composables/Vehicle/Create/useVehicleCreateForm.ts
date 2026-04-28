import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import { store as vehiclesStoreRoute } from '@/routes/user/vehicles';

/**
 * Form Inertia + valeurs initiales + soumission de la page
 * « Nouveau véhicule ». Les valeurs initiales reflètent les enums
 * par défaut (M1 / VP / WLTP / category_1 / Euro 6d-ISC-FCM…).
 */
export function useVehicleCreateForm(): {
    form: InertiaForm<VehicleFormShape>;
    submit: () => void;
} {
    const form = useForm<VehicleFormShape>({
        license_plate: '',
        brand: '',
        model: '',
        vin: '',
        color: '',
        first_french_registration_date: '',
        first_origin_registration_date: '',
        first_economic_use_date: '',
        acquisition_date: '',
        mileage_current: null,
        notes: '',
        reception_category: 'M1',
        vehicle_user_type: 'VP',
        body_type: 'CI',
        seats_count: 5,
        energy_source: 'gasoline',
        euro_standard: 'euro_6d_isc_fcm',
        pollutant_category: 'category_1',
        homologation_method: 'WLTP',
        co2_wltp: null,
        co2_nedc: null,
        taxable_horsepower: null,
    });

    const submit = (): void => {
        form.post(vehiclesStoreRoute.url());
    };

    return { form, submit };
}
