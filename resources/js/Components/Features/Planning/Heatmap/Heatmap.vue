<script setup lang="ts">
/**
 * Heatmap annuelle (CDC § 3.3).
 *
 * Matrice véhicules × 52 semaines avec couleur de densité sur l'échelle
 * blue-50 → blue-950 du design system (8 paliers · 0 → 7 jours utilisés).
 *
 * Layout (refonte D5.10.X · frozen panes Excel-style) ·
 *   - Un seul conteneur scrollable (`max-h-[50em] overflow-auto`).
 *   - Colonne gauche (mini-fiche véhicule) en `sticky left-0` + bg-white ·
 *     reste visible quand on scroll horizontalement.
 *   - Colonne droite (taxe annuelle + jours) en `sticky right-0` + bg-white ·
 *     idem.
 *   - Header (mois) en `sticky top-0` + bg-white · reste visible quand
 *     on scroll verticalement.
 *   - Coins (top-left + top-right) en `sticky top+left/right` avec
 *     z-index supérieur pour couvrir les autres sticky pendant le scroll
 *     croisé.
 *
 * Bénéfice UX · la barre de scroll H est toujours en bas du conteneur
 * (max-h 50em), donc dans le viewport · plus besoin de scroller toute
 * la page jusqu'au bas du tableau pour atteindre la scrollbar
 * horizontale.
 *
 * Clic sur une cellule émet `cell-click` avec { vehicleId, week }.
 *
 * Mode Vue Entreprise (chantier P2) · le composant accepte aussi les
 * DTOs `PlanningHeatmapCompanyVehicleData` (variante company-scoped).
 * Un computed `vehicleViews` normalise la shape avant de la passer aux
 * partials, qui ne connaissent que le type unifié `HeatmapVehicleView`.
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
import type { HeatmapVehicleView } from './types';

type OverviewVehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;
type CompanyVehicle = App.Data.User.Planning.PlanningHeatmapCompanyVehicleData;

const props = defineProps<{
    vehicles: OverviewVehicle[] | CompanyVehicle[];
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

function isCompanyVariant(v: OverviewVehicle | CompanyVehicle): v is CompanyVehicle {
    return 'weeksGlobal' in v;
}

const vehicleViews = computed<HeatmapVehicleView[]>(() =>
    props.vehicles.map((v) => {
        if (isCompanyVariant(v)) {
            return {
                id: v.id,
                licensePlate: v.licensePlate,
                brand: v.brand,
                model: v.model,
                userType: v.userType,
                energy: v.energy,
                co2Method: v.co2Method,
                co2Value: v.co2Value,
                taxableHorsepower: v.taxableHorsepower,
                weeksForColor: v.weeksGlobal,
                weeksForCount: v.weeksForCompany,
                summaryDays: v.daysTotalForCompany,
                summaryTax: v.annualTaxDueForCompany,
                exitDate: v.exitDate,
                weeksWithUnavailability: v.weeksWithUnavailability,
                fullYearTax: v.fullYearTax,
                dailyTaxRate: v.dailyTaxRate,
            };
        }

        return {
            id: v.id,
            licensePlate: v.licensePlate,
            brand: v.brand,
            model: v.model,
            userType: v.userType,
            energy: v.energy,
            co2Method: v.co2Method,
            co2Value: v.co2Value,
            taxableHorsepower: v.taxableHorsepower,
            weeksForColor: v.weeks,
            weeksForCount: v.weeks,
            summaryDays: v.daysTotal,
            summaryTax: v.annualTaxDue,
            exitDate: v.exitDate,
            weeksWithUnavailability: v.weeksWithUnavailability,
            fullYearTax: v.fullYearTax,
            dailyTaxRate: v.dailyTaxRate,
        };
    }),
);

const totalAnnualTax = computed((): number =>
    vehicleViews.value.reduce((sum, v) => sum + v.summaryTax, 0),
);
const totalDays = computed((): number =>
    vehicleViews.value.reduce((sum, v) => sum + v.summaryDays, 0),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Bandeau résumé + légende -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-1">
            <HeatmapSummary
                :vehicles-count="vehicleViews.length"
                :total-days="totalDays"
                :total-annual-tax="totalAnnualTax"
                :fiscal-year="fiscalYear"
            />
            <HeatmapLegend />
        </div>

        <!--
            Container heatmap · scrollable cross-axis, max-h limit.
            Frozen panes obtenus via `position: sticky` cross-pinné
            (top/left, top/right, plain top, plain left/right). Le
            `overflow: auto` sur les deux axes met les scrollbars sur
            les bords du conteneur (toujours visibles dans le viewport
            grâce à `max-h-[50em]`).
        -->
        <div class="max-h-[50em] overflow-auto rounded-xl border border-slate-200 bg-white">
            <!--
                Header row · `sticky top-0` pour rester visible pendant
                le scroll vertical. Z-30 sur les coins, Z-20 sur le
                centre pour rester au-dessus du body pendant le scroll
                vertical.
            -->
            <div class="sticky top-0 z-20 flex bg-white">
                <!-- Coin top-left · sticky cross · couvre VehicleInfo pendant le scroll H -->
                <div class="sticky left-0 z-30 shrink-0 bg-white pt-4 pl-4 pr-3 pb-2">
                    <div class="h-4" />
                </div>
                <!-- Header centre · labels mensuels (largeur fixe HEATMAP_GRID_WIDTH) -->
                <div class="shrink-0 pt-4 pb-2">
                    <div
                        :style="{ width: `${HEATMAP_GRID_WIDTH}px` }"
                        class="flex h-4"
                    >
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
                </div>
                <!-- Coin top-right · sticky cross -->
                <div class="sticky right-0 z-30 shrink-0 bg-white pt-4 pl-3 pr-4 pb-2">
                    <div class="h-4" />
                </div>
            </div>

            <!--
                Body rows · une row par véhicule. Chaque row contient
                3 cells (sticky left + centre scrollable + sticky right).
                Le `border-t border-slate-100` sur le row container
                trace la ligne horizontale entre véhicules.
            -->
            <div
                v-for="view in vehicleViews"
                :key="view.id"
                class="flex border-t border-slate-100"
            >
                <!-- Sticky left · VehicleInfo -->
                <div class="sticky left-0 z-10 shrink-0 bg-white pl-4 pr-3">
                    <VehicleInfo :vehicle-view="view" />
                </div>
                <!-- Centre · WeekCellsRow (largeur fixe HEATMAP_GRID_WIDTH) -->
                <div class="shrink-0">
                    <div :style="{ width: `${HEATMAP_GRID_WIDTH}px` }">
                        <WeekCellsRow
                            :vehicle-view="view"
                            :fiscal-year="fiscalYear"
                            @cell-click="$emit('cell-click', $event)"
                        />
                    </div>
                </div>
                <!-- Sticky right · VehicleSummary -->
                <div class="sticky right-0 z-10 shrink-0 bg-white pl-3 pr-4">
                    <VehicleSummary :vehicle-view="view" />
                </div>
            </div>
        </div>
    </div>
</template>
