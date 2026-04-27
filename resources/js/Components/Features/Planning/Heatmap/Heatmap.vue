<script setup lang="ts">
/**
 * Heatmap annuelle (CDC § 3.3).
 *
 * Matrice véhicules × 52 semaines avec couleur de densité sur l'échelle
 * blue-50 → blue-950 du design system (8 paliers : 0 → 7 jours utilisés).
 *
 * Layout : colonne gauche (mini-fiche véhicule) et colonne droite
 * (taxe annuelle + jours) sticky ; seule la bande centrale (52 semaines)
 * scrolle horizontalement quand la fenêtre est trop étroite.
 *
 * Clic sur une cellule → émet `cell-click` avec { vehicleId, week }.
 */
import { computed } from 'vue';
import {
    HEATMAP_CELL_WIDTH,
    HEATMAP_GRID_WIDTH,
} from '@/Components/Features/Planning/Heatmap/utils/density';
import HeatmapLegend from './partials/HeatmapLegend.vue';
import HeatmapSummary from './partials/HeatmapSummary.vue';
import VehicleInfo from './partials/VehicleInfo.vue';
import VehicleSummary from './partials/VehicleSummary.vue';
import WeekCellsRow from './partials/WeekCellsRow.vue';

type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;

const props = defineProps<{
    vehicles: Vehicle[];
    fiscalYear: number;
}>();

defineEmits<{
    'cell-click': [payload: { vehicleId: number; week: number }];
}>();

const monthLabels = [
    { name: 'Jan', weeks: 4 },
    { name: 'Fév', weeks: 4 },
    { name: 'Mar', weeks: 5 },
    { name: 'Avr', weeks: 4 },
    { name: 'Mai', weeks: 4 },
    { name: 'Juin', weeks: 5 },
    { name: 'Juil', weeks: 4 },
    { name: 'Août', weeks: 4 },
    { name: 'Sept', weeks: 5 },
    { name: 'Oct', weeks: 4 },
    { name: 'Nov', weeks: 4 },
    { name: 'Déc', weeks: 5 },
];

const totalAnnualTax = computed((): number =>
    props.vehicles.reduce((sum, v) => sum + v.annualTaxDue, 0),
);
const totalDays = computed((): number =>
    props.vehicles.reduce((sum, v) => sum + v.daysTotal, 0),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Bandeau résumé + légende -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-1">
            <HeatmapSummary
                :vehicles-count="vehicles.length"
                :total-days="totalDays"
                :total-annual-tax="totalAnnualTax"
                :fiscal-year="fiscalYear"
            />
            <HeatmapLegend />
        </div>

        <!-- Container heatmap : 3 zones (fixe / scrollable / fixe) -->
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-stretch">
                <!-- ZONE GAUCHE FIXE — mini-fiches véhicules -->
                <div class="shrink-0 bg-white pr-3">
                    <div class="mb-2 h-4" />
                    <div class="flex flex-col">
                        <VehicleInfo
                            v-for="vehicle in vehicles"
                            :key="`left-${vehicle.id}`"
                            :vehicle="vehicle"
                        />
                    </div>
                </div>

                <!-- ZONE CENTRE SCROLLABLE — 52 semaines -->
                <div class="min-w-0 flex-1 overflow-x-auto">
                    <div :style="{ width: `${HEATMAP_GRID_WIDTH}px` }">
                        <!-- Labels mensuels -->
                        <div class="mb-2 flex h-4">
                            <div
                                v-for="month in monthLabels"
                                :key="month.name"
                                :style="{
                                    width: `${month.weeks * HEATMAP_CELL_WIDTH}px`,
                                }"
                                class="text-xs font-medium text-slate-500"
                            >
                                {{ month.name }}
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <WeekCellsRow
                                v-for="vehicle in vehicles"
                                :key="`cells-${vehicle.id}`"
                                :vehicle="vehicle"
                                @cell-click="$emit('cell-click', $event)"
                            />
                        </div>
                    </div>
                </div>

                <!-- ZONE DROITE FIXE — taxe annuelle + jours total -->
                <div class="shrink-0 bg-white pl-3">
                    <div class="mb-2 h-4" />
                    <div class="flex flex-col">
                        <VehicleSummary
                            v-for="vehicle in vehicles"
                            :key="`right-${vehicle.id}`"
                            :vehicle="vehicle"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
