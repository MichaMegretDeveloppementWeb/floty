<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type EnumOption = { value: string; label: string };

const props = defineProps<{
    enums: {
        receptionCategories: EnumOption[];
        vehicleUserTypes: EnumOption[];
        bodyTypes: EnumOption[];
        energySources: EnumOption[];
        euroStandards: EnumOption[];
        homologationMethods: EnumOption[];
        pollutantCategories: EnumOption[];
    };
}>();

const form = useForm({
    license_plate: '',
    brand: '',
    model: '',
    vin: '',
    color: '',
    first_french_registration_date: '',
    first_origin_registration_date: '',
    first_economic_use_date: '',
    acquisition_date: '',
    mileage_current: null as number | null,
    notes: '',
    reception_category: 'M1',
    vehicle_user_type: 'VP',
    body_type: 'CI',
    seats_count: 5,
    energy_source: 'gasoline',
    euro_standard: 'euro_6d_isc_fcm',
    pollutant_category: 'category_1',
    homologation_method: 'WLTP',
    co2_wltp: null as number | null,
    co2_nedc: null as number | null,
    taxable_horsepower: null as number | null,
});

const showWltp = computed(() => form.homologation_method === 'WLTP');
const showNedc = computed(() => form.homologation_method === 'NEDC');
const showPa = computed(() => form.homologation_method === 'PA');

const submit = (): void => {
    form.post('/app/vehicles');
};
</script>

<template>
    <Head title="Nouveau véhicule" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Données · Flotte</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Nouveau véhicule
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Les caractéristiques fiscales initiales sont effectives
                    à la date d'acquisition.
                </p>
            </header>

            <form
                class="flex flex-col gap-6 rounded-xl border border-slate-200 bg-white p-6"
                @submit.prevent="submit"
            >
                <section class="flex flex-col gap-4">
                    <p class="eyebrow">Identité</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.license_plate"
                            label="Immatriculation"
                            mono
                            hint="Ex. EH-142-AZ"
                            :error="form.errors.license_plate"
                            required
                        />
                        <TextInput
                            v-model="form.vin"
                            label="VIN"
                            mono
                            :error="form.errors.vin"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.brand"
                            label="Marque"
                            :error="form.errors.brand"
                            required
                        />
                        <TextInput
                            v-model="form.model"
                            label="Modèle"
                            :error="form.errors.model"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <TextInput
                            v-model="form.color"
                            label="Couleur"
                            :error="form.errors.color"
                        />
                        <NumberInput
                            v-model="form.mileage_current"
                            label="Kilométrage actuel"
                            :error="form.errors.mileage_current"
                        >
                            <template #unit>km</template>
                        </NumberInput>
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <p class="eyebrow">Immatriculation et cycle de vie</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <DateInput
                            v-model="form.first_origin_registration_date"
                            label="1ère immatriculation (origine)"
                            :error="form.errors.first_origin_registration_date"
                            required
                        />
                        <DateInput
                            v-model="form.first_french_registration_date"
                            label="1ère immatriculation France"
                            :error="form.errors.first_french_registration_date"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <DateInput
                            v-model="form.first_economic_use_date"
                            label="1ère affectation économique"
                            :error="form.errors.first_economic_use_date"
                            required
                        />
                        <DateInput
                            v-model="form.acquisition_date"
                            label="Date d'acquisition"
                            :error="form.errors.acquisition_date"
                            required
                        />
                    </div>
                </section>

                <section class="flex flex-col gap-4">
                    <p class="eyebrow">
                        Caractéristiques fiscales (version initiale)
                    </p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <SelectInput
                            v-model="form.reception_category"
                            label="Catégorie réception"
                            :options="props.enums.receptionCategories"
                            :error="form.errors.reception_category"
                            required
                        />
                        <SelectInput
                            v-model="form.vehicle_user_type"
                            label="Type utilisateur"
                            :options="props.enums.vehicleUserTypes"
                            :error="form.errors.vehicle_user_type"
                            required
                        />
                        <SelectInput
                            v-model="form.body_type"
                            label="Carrosserie"
                            :options="props.enums.bodyTypes"
                            :error="form.errors.body_type"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <NumberInput
                            v-model="form.seats_count"
                            label="Nombre de places"
                            :min="1"
                            :max="20"
                            :error="form.errors.seats_count"
                            required
                        />
                        <SelectInput
                            v-model="form.energy_source"
                            label="Source d'énergie"
                            :options="props.enums.energySources"
                            :error="form.errors.energy_source"
                            required
                        />
                        <SelectInput
                            v-model="form.euro_standard"
                            label="Norme Euro"
                            :options="props.enums.euroStandards"
                            :error="form.errors.euro_standard"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <SelectInput
                            v-model="form.pollutant_category"
                            label="Catégorie polluants"
                            :options="props.enums.pollutantCategories"
                            :error="form.errors.pollutant_category"
                            required
                        />
                        <SelectInput
                            v-model="form.homologation_method"
                            label="Méthode d'homologation"
                            :options="props.enums.homologationMethods"
                            :error="form.errors.homologation_method"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <NumberInput
                            v-if="showWltp"
                            v-model="form.co2_wltp"
                            label="CO₂ WLTP"
                            :error="form.errors.co2_wltp"
                            required
                        >
                            <template #unit>g/km</template>
                        </NumberInput>
                        <NumberInput
                            v-if="showNedc"
                            v-model="form.co2_nedc"
                            label="CO₂ NEDC"
                            :error="form.errors.co2_nedc"
                            required
                        >
                            <template #unit>g/km</template>
                        </NumberInput>
                        <NumberInput
                            v-if="showPa"
                            v-model="form.taxable_horsepower"
                            label="Puissance admin."
                            :error="form.errors.taxable_horsepower"
                            required
                        >
                            <template #unit>CV</template>
                        </NumberInput>
                    </div>
                </section>

                <div
                    class="flex justify-end gap-3 border-t border-slate-100 pt-4"
                >
                    <Link href="/app/vehicles">
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
