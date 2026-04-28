<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { useVehicleCreateForm } from '@/Composables/Vehicle/Create/useVehicleCreateForm';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import FiscalCharacteristicsSection from './partials/FiscalCharacteristicsSection.vue';
import IdentitySection from './partials/IdentitySection.vue';
import RegistrationSection from './partials/RegistrationSection.vue';

const props = defineProps<{
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const { form, submit } = useVehicleCreateForm();
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
