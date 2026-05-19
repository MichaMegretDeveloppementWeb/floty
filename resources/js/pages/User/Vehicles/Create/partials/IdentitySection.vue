<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { IdCard, ScanLine } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';
import FieldWithManualHint from './FieldWithManualHint.vue';

const props = defineProps<{
    form: InertiaForm<VehicleFormShape>;
    isMissing: (key: keyof VehicleFormShape) => boolean;
    registryLookupEnabled: boolean;
    registryLookupLoading: boolean;
    registryLookupError: string | null;
}>();

const emit = defineEmits<{
    'trigger-registry-lookup': [licensePlate: string];
}>();

const canTriggerLookup = computed(() => {
    if (!props.registryLookupEnabled) {
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

    emit('trigger-registry-lookup', props.form.license_plate);
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

        <div class="flex flex-col gap-3">
            <TextInput
                v-model="form.license_plate"
                label="Immatriculation"
                mono
                hint="Ex. EH-142-AZ"
                :error="form.errors.license_plate"
                required
            />
            <Button
                v-if="registryLookupEnabled"
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
            <p
                v-if="registryLookupError !== null"
                class="text-sm text-amber-700"
            >
                {{ registryLookupError }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2">
            <FieldWithManualHint :active="isMissing('vin')">
                <TextInput
                    v-model="form.vin"
                    label="VIN"
                    mono
                    :error="form.errors.vin"
                />
            </FieldWithManualHint>
            <FieldWithManualHint :active="isMissing('brand')">
                <TextInput
                    v-model="form.brand"
                    label="Marque"
                    :error="form.errors.brand"
                    required
                />
            </FieldWithManualHint>
            <FieldWithManualHint :active="isMissing('model')">
                <TextInput
                    v-model="form.model"
                    label="Modèle"
                    :error="form.errors.model"
                    required
                />
            </FieldWithManualHint>
            <FieldWithManualHint :active="isMissing('color')">
                <TextInput
                    v-model="form.color"
                    label="Couleur"
                    :error="form.errors.color"
                />
            </FieldWithManualHint>
            <FieldWithManualHint :active="isMissing('mileage_current')">
                <NumberInput
                    v-model="form.mileage_current"
                    label="Kilométrage actuel"
                    :error="form.errors.mileage_current"
                >
                    <template #unit>km</template>
                </NumberInput>
            </FieldWithManualHint>
        </div>
    </section>
</template>
