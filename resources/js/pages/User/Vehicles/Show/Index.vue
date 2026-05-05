<script setup lang="ts">
/**
 * Page Show Véhicule — composition en **3 onglets** (chantier η
 * Phase 2 refonte) :
 *
 *   - **Vue d'ensemble** : KPIs + Caractéristiques + Historique +
 *     Utilisation & Répartition + Indispos
 *   - **Fiscalité** : détail du Coût plein (méthode CO₂, polluants,
 *     exonérations, règles appliquées) — sélecteur d'année dédié
 *   - **Facturation** : placeholder V1.2
 *
 * Les sélecteurs d'année des cartes Utilisation et Fiscalité sont
 * **indépendants** — chacun a son propre cache `useYearLazy` (lazy
 * loading + cache client). Pattern aligné Company.
 */
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useVehicleTabs } from '@/Composables/Vehicle/Show/useVehicleTabs';
import VehicleBillingTab from './partials/VehicleBillingTab.vue';
import VehicleFiscalTab from './partials/VehicleFiscalTab.vue';
import VehicleHeader from './partials/VehicleHeader.vue';
import VehicleOverviewTab from './partials/VehicleOverviewTab.vue';
import VehicleTabsNav from './partials/VehicleTabsNav.vue';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const { activeTab, setTab } = useVehicleTabs();
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
            <VehicleFiscalTab
                v-else-if="activeTab === 'fiscal'"
                :vehicle="props.vehicle"
            />
            <VehicleBillingTab v-else-if="activeTab === 'billing'" />
        </div>
    </UserLayout>
</template>
