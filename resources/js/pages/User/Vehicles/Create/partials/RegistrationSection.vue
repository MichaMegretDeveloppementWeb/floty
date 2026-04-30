<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

defineProps<{
    form: InertiaForm<VehicleFormShape>;
}>();
</script>

<template>
    <section class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8">
        <header class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                <CalendarDays :size="18" :stroke-width="1.75" />
            </span>
            <div class="flex flex-col">
                <h2 class="text-base font-semibold text-slate-900">
                    Immatriculation et cycle de vie
                </h2>
                <p class="text-sm text-slate-500">
                    Dates clés utilisées pour les calculs fiscaux.
                </p>
            </div>
        </header>

        <div class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2">
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
