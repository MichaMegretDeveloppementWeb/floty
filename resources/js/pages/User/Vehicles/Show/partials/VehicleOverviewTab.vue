<script setup lang="ts">
/**
 * Onglet Vue d'ensemble de la fiche véhicule (chantier η Phase 2 onglets) :
 * tout ce qui concerne identité + activité globale, en flux vertical
 * pleine largeur.
 *
 *   - KPIs Présent (année courante figée)
 *   - Caractéristiques fiscales (current + history modale, atemporel)
 *   - Historique annuel (mini-tableau Évolution)
 *   - Carte unifiée Utilisation & Répartition (sélecteur d'année local
 *     + lazy loading via fetch JSON, cache client)
 *   - Indispos
 *
 * Pas de sidebar : la fiche véhicule a peu de contenu vraiment candidat
 * « aside » (Indispos seules sont souvent vides ou très courtes →
 * déséquilibre visuel). On préfère la lecture descendante naturelle.
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

        <VehicleYearHistoryCard :history="vehicle.history" />

        <CurrentFiscalCharacteristicsCard
            :fiscal="vehicle.currentFiscalCharacteristics"
            :history="vehicle.fiscalCharacteristicsHistory"
            :options="options"
        />

        <VehicleUsageAndBreakdownCard
            :vehicle-id="vehicle.id"
            :initial-stats="vehicle.usageStats"
            :available-years="vehicle.yearScope.availableYears"
        />

        <UnavailabilitiesCard
            :vehicle-id="vehicle.id"
            :unavailabilities="vehicle.unavailabilities"
            :busy-dates="vehicle.busyDates"
        />
    </div>
</template>
