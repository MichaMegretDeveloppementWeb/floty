<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import type { VehicleEditFormShape } from '@/pages/User/Vehicles/Edit/forms';

defineProps<{
    form: InertiaForm<VehicleEditFormShape>;
}>();
</script>

<template>
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
</template>
