<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/forms';
import FiscalCharacteristicsSection from '@/pages/User/Vehicles/partials/FiscalCharacteristicsSection.vue';
import IdentitySection from '@/pages/User/Vehicles/partials/IdentitySection.vue';
import RegistrationSection from '@/pages/User/Vehicles/partials/RegistrationSection.vue';
import { index as vehiclesIndexRoute, store as vehiclesStoreRoute } from '@/routes/user/vehicles';

const props = defineProps<{
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

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
</script>

<template>
    <Head title="Nouveau véhicule" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Flotte</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                    Nouveau véhicule
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Les caractéristiques fiscales initiales sont effectives à la
                    date d'acquisition.
                </p>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <IdentitySection :form="form" />
                <RegistrationSection :form="form" />
                <FiscalCharacteristicsSection :form="form" :options="props.options" />

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <Link :href="vehiclesIndexRoute.url()">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Enregistrer le véhicule
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
