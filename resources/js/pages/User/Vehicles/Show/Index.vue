<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CompanyFiscalBreakdownTable from './partials/CompanyFiscalBreakdownTable.vue';
import CurrentFiscalCharacteristicsCard from './partials/CurrentFiscalCharacteristicsCard.vue';
import FiscalHistoryTimeline from './partials/FiscalHistoryTimeline.vue';
import FullYearTaxBreakdownPanel from './partials/FullYearTaxBreakdownPanel.vue';
import UnavailabilitiesCard from './partials/UnavailabilitiesCard.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleKpiCards from './partials/VehicleKpiCards.vue';
import VehicleYearlyUsageTimeline from './partials/VehicleYearlyUsageTimeline.vue';

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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Colonne principale -->
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <CurrentFiscalCharacteristicsCard
                        :fiscal="props.vehicle.currentFiscalCharacteristics"
                    />
                    <VehicleYearlyUsageTimeline :stats="props.vehicle.usageStats" />
                    <CompanyFiscalBreakdownTable :stats="props.vehicle.usageStats" />
                    <UnavailabilitiesCard
                        :vehicle-id="props.vehicle.id"
                        :unavailabilities="props.vehicle.unavailabilities"
                    />
                    <FiscalHistoryTimeline
                        :history="props.vehicle.fiscalCharacteristicsHistory"
                    />
                </div>

                <!-- Colonne aside (sticky en lg+) -->
                <aside class="lg:col-span-1">
                    <div class="lg:sticky lg:top-6">
                        <FullYearTaxBreakdownPanel :stats="props.vehicle.usageStats" />
                    </div>
                </aside>
            </div>
        </div>
    </UserLayout>
</template>
