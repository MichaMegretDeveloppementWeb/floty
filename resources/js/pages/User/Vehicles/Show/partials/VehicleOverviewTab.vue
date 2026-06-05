<script setup lang="ts">
/**
 * Overview tab on the vehicle detail page: KPIs, fiscal characteristics
 * card, yearly history and usage + breakdown card. Vehicle events live in
 * their own "Événements" tab; the detailed full-year tax breakdown in the
 * Fiscal tab.
 *
 * The heavy overview data (usage timeline, current-year KPI, history)
 * arrives in the `overview` payload, eager only on the overview tab. The
 * parent gates this component behind a skeleton until the payload is
 * present, so `overview` is always defined here.
 */
import CurrentFiscalCharacteristicsCard from './CurrentFiscalCharacteristicsCard.vue';
import VehicleUsageAndBreakdownCard from './overview/VehicleUsageAndBreakdownCard.vue';
import VehicleKpiCards from './VehicleKpiCards.vue';
import VehicleYearHistoryCard from './VehicleYearHistoryCard.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
    overview: App.Data.User.Vehicle.VehicleOverviewData;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <VehicleKpiCards
            :kpi-stats="props.overview.kpiStats"
            :kpi-year="props.vehicle.kpiYear"
            :kpi-fiscal-available="props.overview.kpiFiscalAvailable"
        />

        <VehicleYearHistoryCard :history="props.overview.history" />

        <CurrentFiscalCharacteristicsCard
            :vehicle-id="props.vehicle.id"
            :fiscal="props.vehicle.currentFiscalCharacteristics"
            :history="props.vehicle.fiscalCharacteristicsHistory"
            :options="props.options"
        />

        <VehicleUsageAndBreakdownCard
            :vehicle-id="props.vehicle.id"
            :initial-stats="props.overview.usageStats"
            :available-years="props.vehicle.yearScope.availableYears"
        />
    </div>
</template>
