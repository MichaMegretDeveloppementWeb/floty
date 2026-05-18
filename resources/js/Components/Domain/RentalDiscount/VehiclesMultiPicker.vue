<script setup lang="ts">
/**
 * Sélecteur multi-véhicules pour le form RentalDiscount (Lot 4 chantier
 * RentalDiscount). Présente la flotte sous forme de liste à cocher avec
 * filtre de recherche (plaque + marque + modèle).
 *
 * UX différente de `DriversMultiPicker` (qui est slot-based) ·
 *  - Le périmètre véhicules est borné (< 100 typique), pas besoin d'une
 *    UX « ajouter une ligne ». Liste à cocher = sélection rapide groupée.
 *  - Affichage de chips au-dessus de la liste pour visualiser
 *    la sélection courante en un coup d'œil.
 *  - Compteur de sélection + bouton « Tout désélectionner ».
 *
 * Émet `update:modelValue` avec un `number[]` (ids sélectionnés, ordre
 * d'ajout préservé).
 */
import { computed, ref } from 'vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';

export type VehicleOption = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: number[];
        vehicles: readonly VehicleOption[];
        /** Désactive l'ensemble (ex. toggle « tous » activé). */
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const search = ref<string>('');

const selectedSet = computed<Set<number>>(() => new Set(props.modelValue));

const filteredVehicles = computed<readonly VehicleOption[]>(() => {
    const term = search.value.trim().toLowerCase();
    if (term === '') {
        return props.vehicles;
    }
    return props.vehicles.filter((v) => {
        const haystack = `${v.licensePlate} ${v.brand} ${v.model}`.toLowerCase();
        return haystack.includes(term);
    });
});

const selectedVehicles = computed<VehicleOption[]>(() =>
    props.modelValue
        .map((id) => props.vehicles.find((v) => v.id === id))
        .filter((v): v is VehicleOption => v !== undefined),
);

function toggle(vehicleId: number): void {
    if (props.disabled) {
        return;
    }
    const set = new Set(props.modelValue);
    if (set.has(vehicleId)) {
        set.delete(vehicleId);
    } else {
        set.add(vehicleId);
    }
    emit('update:modelValue', [...set]);
}

function clearAll(): void {
    if (props.disabled) {
        return;
    }
    emit('update:modelValue', []);
}
</script>

<template>
    <div :class="['flex flex-col gap-3', disabled ? 'opacity-50 pointer-events-none' : '']">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-slate-500">
                <span v-if="selectedVehicles.length > 0">
                    {{ selectedVehicles.length }} véhicule{{ selectedVehicles.length > 1 ? 's' : '' }} sélectionné{{ selectedVehicles.length > 1 ? 's' : '' }}
                </span>
                <span v-else class="italic">
                    Aucun véhicule sélectionné
                </span>
            </p>
            <button
                v-if="selectedVehicles.length > 0"
                type="button"
                class="text-xs font-medium text-slate-600 hover:text-slate-900 underline"
                @click="clearAll"
            >
                Tout désélectionner
            </button>
        </div>

        <SearchInput
            v-model="search"
            placeholder="Filtrer par plaque, marque ou modèle"
            aria-label="Filtrer la liste des véhicules"
        />

        <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200">
            <ul v-if="filteredVehicles.length > 0" class="divide-y divide-slate-100">
                <li
                    v-for="vehicle in filteredVehicles"
                    :key="vehicle.id"
                >
                    <label
                        class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-slate-50"
                        :class="selectedSet.has(vehicle.id) ? 'bg-blue-50/50' : ''"
                    >
                        <input
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                            :checked="selectedSet.has(vehicle.id)"
                            :disabled="disabled"
                            @change="toggle(vehicle.id)"
                        >
                        <span class="font-mono text-xs font-medium text-slate-900">
                            {{ vehicle.licensePlate }}
                        </span>
                        <span class="text-slate-600">
                            {{ vehicle.brand }} {{ vehicle.model }}
                        </span>
                    </label>
                </li>
            </ul>
            <div v-else class="px-4 py-6 text-center text-xs text-slate-500">
                Aucun véhicule ne correspond au filtre.
            </div>
        </div>
    </div>
</template>
