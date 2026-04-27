<script setup lang="ts">
import { computed } from 'vue';
import MultiDatePicker from '@/Components/Features/Planning/MultiDatePicker.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';

type VehicleOption = App.Data.User.Vehicle.VehicleOptionData;
type CompanyOption = App.Data.User.Company.CompanyOptionData;

const props = defineProps<{
    vehicles: VehicleOption[];
    companies: CompanyOption[];
    fiscalYear: number;
    selectedVehicleId: number | null;
    selectedCompanyId: number | null;
    selectedDates: string[];
    disabledDates: string[];
    pairDates: string[];
}>();

const emit = defineEmits<{
    'update:selectedVehicleId': [value: number | null];
    'update:selectedCompanyId': [value: number | null];
    'update:selectedDates': [value: string[]];
}>();

const vehicleOptions = computed(() =>
    props.vehicles.map((v) => ({
        value: String(v.id),
        label: v.label,
    })),
);

const companyOptions = computed(() =>
    props.companies.map((c) => ({
        value: String(c.id),
        label: `${c.shortCode} — ${c.legalName}`,
    })),
);

const vehicleIdString = computed({
    get: () =>
        props.selectedVehicleId !== null ? String(props.selectedVehicleId) : '',
    set: (v: string) =>
        emit('update:selectedVehicleId', v === '' ? null : Number(v)),
});

const companyIdString = computed({
    get: () =>
        props.selectedCompanyId !== null ? String(props.selectedCompanyId) : '',
    set: (v: string) =>
        emit('update:selectedCompanyId', v === '' ? null : Number(v)),
});

const datesProxy = computed({
    get: () => props.selectedDates,
    set: (v: string[]) => emit('update:selectedDates', v),
});
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5"
    >
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <SelectInput
                v-model="vehicleIdString"
                label="Véhicule"
                placeholder="Choisir un véhicule…"
                :options="vehicleOptions"
            />
            <SelectInput
                v-model="companyIdString"
                label="Entreprise utilisatrice"
                placeholder="Choisir une entreprise…"
                :options="companyOptions"
            />
        </div>

        <div
            v-if="selectedVehicleId === null"
            class="rounded-lg bg-slate-50 p-6 text-center text-sm text-slate-500"
        >
            Sélectionnez un véhicule pour accéder au calendrier.
        </div>
        <MultiDatePicker
            v-else
            v-model:selected="datesProxy"
            :year="fiscalYear"
            :disabled-dates="disabledDates"
            :current-pair-dates="pairDates"
        />
    </section>
</template>
