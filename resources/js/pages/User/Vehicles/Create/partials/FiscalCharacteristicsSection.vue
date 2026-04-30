<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import {
    derivePollutantCategory,
    requiresUnderlyingCombustionEngine,
} from '@/Utils/derivePollutantCategory';

type SelectOption = { value: string; label: string };

const props = defineProps<{
    form: InertiaForm<VehicleFormShape>;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

// Affichage conditionnel des champs CO₂/CV selon la méthode d'homologation
// retenue (R-2024-005/006). On ne rend qu'un seul des trois inputs.
const showWltp = computed((): boolean => props.form.homologation_method === 'WLTP');
const showNedc = computed((): boolean => props.form.homologation_method === 'NEDC');
const showPa = computed((): boolean => props.form.homologation_method === 'PA');

const isHybrid = computed((): boolean =>
    requiresUnderlyingCombustionEngine(props.form.energy_source),
);

const derivedPollutantCategoryValue = computed<App.Enums.Vehicle.PollutantCategory>(
    () => derivePollutantCategory(
        props.form.energy_source,
        props.form.euro_standard,
        props.form.underlying_combustion_engine_type,
    ),
);

const pollutantCategoryLabel = computed((): string => {
    const target = derivedPollutantCategoryValue.value;
    const found = props.options.pollutantCategories.find(
        (option: SelectOption) => option.value === target,
    );

    return found?.label ?? target;
});
</script>

<template>
    <section class="flex flex-col gap-4">
        <p class="eyebrow">Caractéristiques fiscales (version initiale)</p>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <SelectInput
                v-model="form.reception_category"
                label="Catégorie réception"
                :options="options.receptionCategories"
                :error="form.errors.reception_category"
                required
            />
            <SelectInput
                v-model="form.vehicle_user_type"
                label="Type utilisateur"
                :options="options.vehicleUserTypes"
                :error="form.errors.vehicle_user_type"
                required
            />
            <SelectInput
                v-model="form.body_type"
                label="Carrosserie"
                :options="options.bodyTypes"
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
                :options="options.energySources"
                :error="form.errors.energy_source"
                required
            />
            <SelectInput
                v-model="form.euro_standard"
                label="Norme Euro"
                :options="options.euroStandards"
                :error="form.errors.euro_standard"
            />
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <SelectInput
                v-if="isHybrid"
                v-model="form.underlying_combustion_engine_type"
                label="Moteur thermique sous-jacent"
                :options="options.underlyingCombustionEngineTypes"
                :error="form.errors.underlying_combustion_engine_type"
                hint="Indispensable pour catégoriser un hybride : essence → Catégorie 1, Diesel → Plus polluants."
                required
            />
            <SelectInput
                v-model="form.homologation_method"
                label="Méthode d'homologation"
                :options="options.homologationMethods"
                :error="form.errors.homologation_method"
                required
            />
        </div>
        <div
            class="flex flex-col gap-1 rounded-lg bg-slate-50/70 px-3 py-3"
            aria-live="polite"
        >
            <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">
                Catégorie polluants (calculée)
            </p>
            <p class="text-base font-semibold text-slate-900">
                {{ pollutantCategoryLabel }}
            </p>
            <p class="text-xs leading-snug text-slate-500">
                Dérivée automatiquement de la source d'énergie, de la norme Euro
                et du moteur thermique sous-jacent. Non saisissable.
            </p>
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
</template>
