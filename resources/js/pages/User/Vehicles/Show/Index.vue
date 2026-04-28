<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CurrentFiscalCharacteristicsCard from './partials/CurrentFiscalCharacteristicsCard.vue';
import FiscalHistoryTimeline from './partials/FiscalHistoryTimeline.vue';
import VehicleSummary from './partials/VehicleSummary.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();
</script>

<template>
    <Head :title="`${props.vehicle.licensePlate} — ${props.vehicle.brand} ${props.vehicle.model}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <VehicleSummary :vehicle="props.vehicle" />
            <CurrentFiscalCharacteristicsCard
                :fiscal="props.vehicle.currentFiscalCharacteristics"
            />
            <FiscalHistoryTimeline
                :history="props.vehicle.fiscalCharacteristicsHistory"
            />
        </div>
    </UserLayout>
</template>
