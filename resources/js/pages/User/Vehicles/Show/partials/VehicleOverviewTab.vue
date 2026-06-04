<script setup lang="ts">
/**
 * Overview tab on the vehicle detail page: KPIs, fiscal characteristics
 * card, yearly history and usage + breakdown card. Vehicle events live in
 * their own "Événements" tab; the detailed full-year tax breakdown in the
 * Fiscal tab.
 */
import { Deferred } from '@inertiajs/vue3';
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';
import CurrentFiscalCharacteristicsCard from './CurrentFiscalCharacteristicsCard.vue';
import VehicleUsageAndBreakdownCard from './overview/VehicleUsageAndBreakdownCard.vue';
import VehicleKpiCards from './VehicleKpiCards.vue';
import VehicleYearHistoryCard from './VehicleYearHistoryCard.vue';

defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
    // Deferred: arrives on second round-trip, <Deferred data="history">
    // shows a skeleton in the meantime.
    history?: App.Data.User.Vehicle.VehicleYearStatsData[];
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <VehicleKpiCards
            :kpi-stats="vehicle.kpiStats"
            :kpi-year="vehicle.kpiYear"
            :kpi-fiscal-available="vehicle.kpiFiscalAvailable"
        />

        <Deferred data="history">
            <template #fallback>
                <Skeleton class="h-32 rounded-xl" />
            </template>
            <VehicleYearHistoryCard :history="history!" />
        </Deferred>

        <CurrentFiscalCharacteristicsCard
            :vehicle-id="vehicle.id"
            :fiscal="vehicle.currentFiscalCharacteristics"
            :history="vehicle.fiscalCharacteristicsHistory"
            :options="options"
        />

        <VehicleUsageAndBreakdownCard
            :vehicle-id="vehicle.id"
            :initial-stats="vehicle.usageStats"
            :available-years="vehicle.yearScope.availableYears"
        />
    </div>
</template>
