<script setup lang="ts">
/**
 * Vehicle detail page split into 3 tabs (Overview, Fiscal, Billing).
 * Tabs are lazy + cumulative: only the active tab's props are eager,
 * the others arrive via partial reload on first visit.
 */
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useVehicleTabs } from '@/Composables/Vehicle/Show/useVehicleTabs';
import TabLoadingSkeleton from '@/pages/User/Companies/Show/partials/TabLoadingSkeleton.vue';
import VehicleBillingTab from './partials/VehicleBillingTab.vue';
import VehicleEventsTimeline from './partials/VehicleEventsTimeline.vue';
import VehicleFiscalTab from './partials/VehicleFiscalTab.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleOverviewTab from './partials/VehicleOverviewTab.vue';
import VehicleTabsNav from './partials/VehicleTabsNav.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
    billingYear: number;
    fiscalYear: number;
    fiscalYearScope: App.Data.Shared.YearScopeData;

    // Deferred: arrives on second round-trip, consumed via <Deferred data="history">.
    history?: App.Data.User.Vehicle.VehicleYearStatsData[];

    // Lazy: populated after the matching tab is first visited.
    vehicleBilling?: App.Data.User.Billing.MonthlyBillingBreakdownData;
    fiscalYearBreakdown?: App.Data.User.Vehicle.VehicleFullYearTaxBreakdownData;
}>();

const { activeTab, setTab, loadingTab } = useVehicleTabs();
</script>

<template>
    <Head :title="`${props.vehicle.licensePlate} · ${props.vehicle.brand} ${props.vehicle.model}`" />

    <UserLayout>
        <div class="flex flex-col gap-6 max-w-[80em] m-auto">
            <VehicleHeader :vehicle="props.vehicle" />

            <VehicleTabsNav :active-tab="activeTab" @change="setTab" />

            <VehicleOverviewTab
                v-if="activeTab === 'overview'"
                :vehicle="props.vehicle"
                :options="props.options"
                :history="props.history"
            />

            <VehicleEventsTimeline
                v-else-if="activeTab === 'events'"
                :vehicle-id="props.vehicle.id"
                :vehicle-events="props.vehicle.vehicleEvents"
            />

            <!--
                `loadingTab !== '<key>'` forces a fresh remount during a
                partial reload, otherwise inner year selectors stay bound
                to the previous props.
            -->
            <template v-else-if="activeTab === 'fiscal'">
                <VehicleFiscalTab
                    v-if="props.fiscalYearBreakdown && loadingTab !== 'fiscal'"
                    :vehicle="props.vehicle"
                    :fiscal-year-breakdown="props.fiscalYearBreakdown"
                    :fiscal-year="props.fiscalYear"
                    :fiscal-year-scope="props.fiscalYearScope"
                />
                <TabLoadingSkeleton v-else />
            </template>

            <template v-else-if="activeTab === 'billing'">
                <VehicleBillingTab
                    v-if="props.vehicleBilling && loadingTab !== 'billing'"
                    :vehicle-id="props.vehicle.id"
                    :pricings="props.vehicle.yearlyPricings"
                    :monthly-billing="props.vehicleBilling"
                    :year-scope="props.vehicle.yearScope"
                    :active-year="props.billingYear"
                />
                <TabLoadingSkeleton v-else />
            </template>
        </div>
    </UserLayout>
</template>
