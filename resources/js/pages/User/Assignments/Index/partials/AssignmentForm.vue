<script setup lang="ts">
import MultiDatePicker from '@/Components/Features/Planning/MultiDatePicker.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import { useAssignmentFormShape } from '@/Composables/Assignment/Index/useAssignmentFormShape';

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

const {
    vehicleOptions,
    companyOptions,
    vehicleIdString,
    companyIdString,
    datesProxy,
} = useAssignmentFormShape(props, emit);
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
