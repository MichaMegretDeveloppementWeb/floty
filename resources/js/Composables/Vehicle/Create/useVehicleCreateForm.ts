import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import { store as vehiclesStoreRoute } from '@/routes/user/vehicles';

/**
 * Form Inertia + valeurs initiales + soumission de la page
 * « Nouveau véhicule ». La catégorie polluants n'est pas saisie -
 * elle est dérivée côté backend par le Repository à partir de
 * `energy_source`, `euro_standard` et `underlying_combustion_engine_type`.
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
        underlying_combustion_engine_type: '',
        euro_standard: 'euro_6d_isc_fcm',
        homologation_method: 'WLTP',
        co2_wltp: null,
        co2_nedc: null,
        taxable_horsepower: null,
        kerb_mass: null,
        handicap_access: false,
        m1_special_use: false,
        n1_passenger_transport: false,
        n1_removable_second_row_seat: false,
        n1_ski_lift_use: false,
    });

    // Watchers anti-données fantômes : les flags M1/N1 propres à une
    // catégorie/carrosserie sont remis à false dès que l'utilisateur
    // bascule vers une combinaison où ils ne s'appliquent plus.
    watch(
        () => form.reception_category,
        (cat) => {
            if (cat !== 'M1') {
                form.m1_special_use = false;
            }

            if (cat !== 'N1') {
                form.n1_passenger_transport = false;
                form.n1_removable_second_row_seat = false;
                form.n1_ski_lift_use = false;
            }
        },
    );

    watch(
        () => form.body_type,
        (body) => {
            if (body !== 'CTTE') {
                form.n1_passenger_transport = false;
                form.n1_removable_second_row_seat = false;
            }

            if (body !== 'BE') {
                form.n1_ski_lift_use = false;
            }
        },
    );

    const submit = (): void => {
        form
            .transform((data) => ({
                ...data,
                underlying_combustion_engine_type:
                    data.underlying_combustion_engine_type === ''
                        ? null
                        : data.underlying_combustion_engine_type,
                euro_standard: data.euro_standard === '' ? null : data.euro_standard,
            }))
            .post(vehiclesStoreRoute.url());
    };

    return { form, submit };
}
