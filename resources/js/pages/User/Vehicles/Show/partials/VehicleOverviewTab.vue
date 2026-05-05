<script setup lang="ts">
/**
 * Onglet Vue d'ensemble de la fiche véhicule (chantier η Phase 2 onglets) :
 * tout ce qui concerne identité + activité globale.
 *
 *   - KPIs Présent (année courante figée — pleine largeur)
 *   - Grid xl:3cols :
 *       Col 1-2 : Caractéristiques fiscales + Historique + carte unifiée
 *                 Utilisation & Répartition
 *       Col 3   : Indispos en aside (xl+) ; en < xl, l'aside est masqué
 *                 et un Indispos en pleine largeur s'affiche en bas du
 *                 main pour préserver le scroll vertical naturel mobile.
 *
 * Le panel détaillé du Coût plein vit dans l'onglet Fiscalité.
 */
import CurrentFiscalCharacteristicsCard from './CurrentFiscalCharacteristicsCard.vue';
import VehicleUsageAndBreakdownCard from './overview/VehicleUsageAndBreakdownCard.vue';
import UnavailabilitiesCard from './UnavailabilitiesCard.vue';
import VehicleKpiCards from './VehicleKpiCards.vue';
import VehicleYearHistoryCard from './VehicleYearHistoryCard.vue';

defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <VehicleKpiCards
            :kpi-stats="vehicle.kpiStats"
            :kpi-year="vehicle.kpiYear"
            :kpi-fiscal-available="vehicle.kpiFiscalAvailable"
        />

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Colonne principale -->
            <div class="flex flex-col gap-6 xl:col-span-2">
                <CurrentFiscalCharacteristicsCard
                    :fiscal="vehicle.currentFiscalCharacteristics"
                    :history="vehicle.fiscalCharacteristicsHistory"
                    :options="options"
                />

                <VehicleYearHistoryCard :history="vehicle.history" />

                <VehicleUsageAndBreakdownCard
                    :vehicle-id="vehicle.id"
                    :initial-stats="vehicle.usageStats"
                    :available-years="vehicle.yearScope.availableYears"
                />

                <!-- < xl : Indispos dans le main, sous Utilisation. En xl+,
                     c'est l'aside qui la porte. -->
                <UnavailabilitiesCard
                    class="xl:hidden"
                    :vehicle-id="vehicle.id"
                    :unavailabilities="vehicle.unavailabilities"
                    :busy-dates="vehicle.busyDates"
                />
            </div>

            <!-- Aside Indispos visible xl+ uniquement -->
            <aside class="hidden xl:col-span-1 xl:block">
                <UnavailabilitiesCard
                    :vehicle-id="vehicle.id"
                    :unavailabilities="vehicle.unavailabilities"
                    :busy-dates="vehicle.busyDates"
                />
            </aside>
        </div>
    </div>
</template>
