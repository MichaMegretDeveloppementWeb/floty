<script setup lang="ts">
/**
 * Heatmap annuelle (CDC § 3.3).
 *
 * Matrice véhicules × 52 semaines avec couleur de densité sur l'échelle
 * blue-50 → blue-950 du design system (8 paliers · 0 → 7 jours utilisés).
 *
 * Layout (refonte D5.10.X · frozen panes Excel-style avec `<table>`) ·
 *   - Un seul conteneur scrollable extérieur · `max-h-[50em]
 *     overflow-auto`. Scrollbars V à droite et H en bas du conteneur,
 *     toujours dans le viewport (50em ≈ 800px).
 *   - Vraie `<table>` HTML avec `border-collapse: separate` (requis pour
 *     que `position: sticky` fonctionne sur les cells). Largeurs des
 *     colonnes calculées par le navigateur · garantit l'alignement
 *     parfait entre header et body (résout les bugs décalage).
 *   - Colonne gauche (VehicleInfo) en `position: sticky; left: 0` +
 *     `bg-white` · reste visible pendant le scroll H.
 *   - Colonne droite (VehicleSummary) en `position: sticky; right: 0` +
 *     `bg-white` · idem.
 *   - Header (labels mensuels) en `position: sticky; top: 0` · reste
 *     visible pendant le scroll V.
 *   - Coins (top-left + top-right) en sticky double avec z-index
 *     supérieur · couvrent les autres sticky pendant le scroll croisé.
 *
 * Z-index policy ·
 *   - thead cells coin (sticky top + left/right) · z-30
 *   - thead cells centre (sticky top seul) · z-20
 *   - tbody cells sticky (left/right seul) · z-10
 *   - tbody cells centre · z-0 (default)
 *
 * Bénéfice UX · la barre de scroll H est toujours dans le viewport,
 * peu importe la position verticale dans la liste de véhicules. Plus
 * besoin de descendre jusqu'au dernier véhicule pour atteindre la
 * scrollbar horizontale.
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
            Container heatmap · max-h 50em + overflow auto sur les deux
            axes. Le `<table>` interne gère l'alignement des colonnes
            entre rows automatiquement, ce qui résout les bugs décalage
            header / cellules / colonne droite du précédent essai en
            flex.
        -->
        <div class="max-h-[50em] overflow-auto rounded-xl border border-slate-200 bg-white">
            <table class="border-separate border-spacing-0">
                <thead>
                    <tr>
                        <!--
                            Coin top-left · sticky top + sticky left.
                            z-30 pour couvrir les sticky purs pendant
                            le scroll croisé.
                        -->
                        <th class="sticky top-0 left-0 z-30 bg-white border-b border-slate-100 pt-4 pl-4 pr-3 pb-2 text-left font-normal">
                            <div class="h-4" />
                        </th>
                        <!-- Header centre · labels mensuels alignés sur les cellules -->
                        <th
                            class="sticky top-0 z-20 bg-white border-b border-slate-100 pt-4 pb-2 text-left font-normal"
                            :style="{ width: `${HEATMAP_GRID_WIDTH}px`, minWidth: `${HEATMAP_GRID_WIDTH}px` }"
                        >
                            <div class="flex h-4">
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
                        </th>
                        <!-- Coin top-right · sticky top + sticky right · z-30 -->
                        <th class="sticky top-0 right-0 z-30 bg-white border-b border-slate-100 pt-4 pl-3 pr-4 pb-2 text-right font-normal">
                            <div class="h-4" />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(view, idx) in vehicleViews"
                        :key="view.id"
                    >
                        <!--
                            Sticky left · VehicleInfo. `border-t` sur
                            chaque cell de chaque row (sauf row 0) pour
                            tracer la séparation horizontale entre
                            véhicules. Avec `border-collapse: separate`
                            on doit gérer le border au niveau cell.
                        -->
                        <td
                            class="sticky left-0 z-10 bg-white pl-4 pr-3 align-middle"
                            :class="idx > 0 && 'border-t border-slate-100'"
                        >
                            <VehicleInfo :vehicle-view="view" />
                        </td>
                        <td
                            class="align-middle"
                            :class="idx > 0 && 'border-t border-slate-100'"
                            :style="{ width: `${HEATMAP_GRID_WIDTH}px`, minWidth: `${HEATMAP_GRID_WIDTH}px` }"
                        >
                            <WeekCellsRow
                                :vehicle-view="view"
                                :fiscal-year="fiscalYear"
                                @cell-click="$emit('cell-click', $event)"
                            />
                        </td>
                        <td
                            class="sticky right-0 z-10 bg-white pl-3 pr-4 align-middle"
                            :class="idx > 0 && 'border-t border-slate-100'"
                        >
                            <VehicleSummary :vehicle-view="view" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
