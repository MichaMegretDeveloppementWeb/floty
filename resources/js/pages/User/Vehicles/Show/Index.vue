<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CompanyUsageBreakdown from './partials/CompanyUsageBreakdown.vue';
import CurrentFiscalCharacteristicsCard from './partials/CurrentFiscalCharacteristicsCard.vue';
import FiscalHistoryTimeline from './partials/FiscalHistoryTimeline.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleKpiCards from './partials/VehicleKpiCards.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();
</script>

<template>
    <Head :title="`${props.vehicle.licensePlate} — ${props.vehicle.brand} ${props.vehicle.model}`" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <VehicleHeader :vehicle="props.vehicle" />
            <VehicleKpiCards :stats="props.vehicle.usageStats" />
            <CurrentFiscalCharacteristicsCard
                :fiscal="props.vehicle.currentFiscalCharacteristics"
            />
            <CompanyUsageBreakdown :stats="props.vehicle.usageStats" />
            <FiscalHistoryTimeline
                :history="props.vehicle.fiscalCharacteristicsHistory"
            />
        </div>
    </UserLayout>
</template>
