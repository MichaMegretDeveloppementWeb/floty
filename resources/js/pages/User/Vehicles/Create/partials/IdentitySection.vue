<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { IdCard } from 'lucide-vue-next';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

defineProps<{
    form: InertiaForm<VehicleFormShape>;
}>();
</script>

<template>
    <section class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8">
        <header class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                <IdCard :size="18" :stroke-width="1.75" />
            </span>
            <div class="flex flex-col">
                <h2 class="text-base font-semibold text-slate-900">
                    Identité du véhicule
                </h2>
                <p class="text-sm text-slate-500">
                    Plaque, marque, modèle et options secondaires.
                </p>
            </div>
        </header>

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
