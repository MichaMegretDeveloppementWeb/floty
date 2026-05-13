<script setup lang="ts">
/**
 * Page Show Véhicule · composition en **3 onglets** (chantier η
 * Phase 2 refonte) :
 *
 *   - **Vue d'ensemble** : KPIs + Caractéristiques + Historique +
 *     Utilisation & Répartition + Indispos
 *   - **Fiscalité** : détail de la Taxe pleine · sélecteur d'année dédié
 *   - **Facturation** : tarifs annuels + recettes mensuelles
 *
 * D5.10.V · chargement lazy + cumulatif des onglets. Seules les props
 * de l'onglet actif au mount sont eager (cf. `VehicleController::show`).
 * Cf. doctrine `useCompanyTabs` pour le détail.
 */
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useVehicleTabs } from '@/Composables/Vehicle/Show/useVehicleTabs';
import TabLoadingSkeleton from '@/pages/User/Companies/Show/partials/TabLoadingSkeleton.vue';
import VehicleBillingTab from './partials/VehicleBillingTab.vue';
import VehicleFiscalTab from './partials/VehicleFiscalTab.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleOverviewTab from './partials/VehicleOverviewTab.vue';
import VehicleTabsNav from './partials/VehicleTabsNav.vue';

const props = defineProps<{
    // Eager · toujours présentes.
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
    billingYear: number;
    fiscalYear: number;

    // Lazy · présentes uniquement après visite de leur onglet.
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
            />

            <!--
                D5.10.V · `loadingTab !== '<key>'` force le skeleton
                pendant un partial reload (onglet stale) · remount frais
                après reload, sinon les sélecteurs d'année initialisés
                sur les vieux props ne se resyncent pas avec les
                nouveaux. Cf. doctrine `useCompanyTabs`.
            -->
            <template v-else-if="activeTab === 'fiscal'">
                <VehicleFiscalTab
                    v-if="props.fiscalYearBreakdown && loadingTab !== 'fiscal'"
                    :vehicle="props.vehicle"
                    :fiscal-year-breakdown="props.fiscalYearBreakdown"
                    :fiscal-year="props.fiscalYear"
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
