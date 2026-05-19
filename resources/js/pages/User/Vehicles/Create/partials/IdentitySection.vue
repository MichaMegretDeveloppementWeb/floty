<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { IdCard, ScanLine } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useVehicleRegistryLookup } from '@/Composables/Vehicle/Create/useVehicleRegistryLookup';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

const props = defineProps<{
    form: InertiaForm<VehicleFormShape>;
}>();

const page = usePage();
const registryLookupEnabled = computed(
    () => (page.props as Record<string, unknown>).vehicleRegistryLookupEnabled === true,
);

const { loading: registryLookupLoading, lookup: registryLookup } =
    useVehicleRegistryLookup(props.form);

const canTriggerLookup = computed(() => {
    if (!registryLookupEnabled.value) {
        return false;
    }

    return props.form.license_plate.replace(/\s+/g, '').length >= 4;
});

/**
 * Trigger the registry lookup if the form is ready.
 */
function triggerLookup(): void {
    if (!canTriggerLookup.value) {
        return;
    }

    void registryLookup(props.form.license_plate);
}
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

        <div
            v-if="registryLookupEnabled"
            class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 p-4"
        >
            <p class="text-sm text-slate-600">
                Saisissez la plaque puis cliquez sur le bouton ci-dessous pour pré-remplir le formulaire.
            </p>
            <Button
                type="button"
                variant="secondary"
                :disabled="!canTriggerLookup"
                :loading="registryLookupLoading"
                class="self-start"
                @click="triggerLookup"
            >
                <ScanLine :size="16" :stroke-width="1.75" />
                Pré-remplir depuis la carte grise
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2">
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
