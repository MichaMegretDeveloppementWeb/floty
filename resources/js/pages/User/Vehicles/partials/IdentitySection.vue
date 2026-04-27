<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/forms';

defineProps<{
    form: InertiaForm<VehicleFormShape>;
}>();
</script>

<template>
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
</template>
