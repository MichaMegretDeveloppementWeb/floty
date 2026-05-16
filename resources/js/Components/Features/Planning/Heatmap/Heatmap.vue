<script setup lang="ts">
/**
 * Heatmap annuelle (CDC § 3.3).
 *
 * Matrice véhicules × 52 semaines avec couleur de densité sur l'échelle
 * blue-50 → blue-950 du design system (8 paliers · 0 → 7 jours utilisés).
 *
 * Layout (refonte D5.10.X · 3 blocs synchronisés) ·
 *   - Bloc gauche (mini-fiche véhicule), bloc central (cellules 52
 *     semaines), bloc droit (taxe annuelle + jours) · chacun a son
 *     propre `max-h-[50em] overflow-y-auto`. Hauteur visible alignée
 *     entre les 3 blocs.
 *   - Le bloc central a en plus `overflow-x-auto` · sa scrollbar
 *     horizontale est physiquement **au bas du bloc central**, sur la
 *     largeur du bloc central uniquement (pas de bricolage avec une
 *     scrollbar qui dépasse).
 *   - La scrollbar verticale est masquée sur les blocs gauche et
 *     central (CSS `scrollbar-hide`) · seule celle du bloc droit est
 *     visible, à la lisière droite du conteneur. Visuellement on a
 *     l'impression d'une scrollbar V unique sur le conteneur.
 *   - Le scroll V est synchronisé entre les 3 blocs via un scroll event
 *     listener · scroller dans n'importe lequel propage le scrollTop
 *     aux deux autres. Un flag `syncing` + `requestAnimationFrame`
 *     évite le feedback loop.
 *   - Header (labels mensuels) en `sticky top-0` dans chaque bloc ·
 *     les 3 stickys sont visuellement alignés grâce à un placeholder
 *     de hauteur identique dans les blocs gauche/droit.
 *
 * Bénéfice UX · pattern visuellement attendu (gauche fixe / centre
 * scrollable / droite fixe) sans triche · la scrollbar H correspond
 * **strictement** à la largeur du bloc central scrollable, pas au
 * conteneur entier.
 *
 * Clic sur une cellule émet `cell-click` avec { vehicleId, week }.
 *
 * Mode Vue Entreprise (chantier P2) · le composant accepte aussi les
 * DTOs `PlanningHeatmapCompanyVehicleData` (variante company-scoped).
 * Un computed `vehicleViews` normalise la shape avant de la passer aux
 * partials, qui ne connaissent que le type unifié `HeatmapVehicleView`.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    HEATMAP_CELL_WIDTH,
    HEATMAP_GRID_WIDTH,
} from '@/Components/Features/Planning/Heatmap/utils/density';
import HeatmapLegend from './partials/HeatmapLegend.vue';
import HeatmapSummary from './partials/HeatmapSummary.vue';
import VehicleInfo from './partials/VehicleInfo.vue';
import VehicleSummary from './partials/VehicleSummary.vue';
import WeekCellsRow from './partials/WeekCellsRow.vue';
import type { HeatmapCosts, HeatmapVehicleView } from './types';

type OverviewVehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;
type CompanyVehicle = App.Data.User.Planning.PlanningHeatmapCompanyVehicleData;

const props = defineProps<{
    vehicles: OverviewVehicle[] | CompanyVehicle[];
    fiscalYear: number;
    /**
     * Map des coûts fiscaux par véhicule, servie en `Inertia::defer`
     * côté controller. `undefined` au mount initial · les partials
     * VehicleInfo / VehicleSummary affichent un skeleton tant que les
     * valeurs ne sont pas hydratées (chantier perf 2026-05-16).
     */
    costs?: HeatmapCosts;
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
        const c = props.costs?.[v.id] ?? null;
        const summaryTax = c?.annualTaxDue ?? null;
        const fullYearTax = c?.fullYearTax ?? null;
        const dailyTaxRate = c?.dailyTaxRate ?? null;

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
                summaryTax,
                exitDate: v.exitDate,
                weeksWithUnavailability: v.weeksWithUnavailability,
                fullYearTax,
                dailyTaxRate,
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
            summaryTax,
            exitDate: v.exitDate,
            weeksWithUnavailability: v.weeksWithUnavailability,
            fullYearTax,
            dailyTaxRate,
        };
    }),
);

/**
 * Total flotte (€) · null tant qu'au moins une ligne attend ses costs
 * (1ʳᵉ RTT). Le partial `HeatmapSummary` affiche un skeleton inline
 * dans ce cas.
 */
const totalAnnualTax = computed((): number | null => {
    if (vehicleViews.value.some((v) => v.summaryTax === null)) {
        return null;
    }

    return vehicleViews.value.reduce((sum, v) => sum + (v.summaryTax ?? 0), 0);
});
const totalDays = computed((): number =>
    vehicleViews.value.reduce((sum, v) => sum + v.summaryDays, 0),
);

/**
 * Synchronisation du scroll vertical entre les 3 blocs (gauche, centre,
 * droite). Le flag `syncing` empêche le feedback loop (sinon set
 * scrollTop sur un élément déclenche un event scroll qui déclencherait
 * à son tour la sync, etc.).
 */
const leftRef = ref<HTMLElement | null>(null);
const middleRef = ref<HTMLElement | null>(null);
const rightRef = ref<HTMLElement | null>(null);

/**
 * Largeur RÉELLE de la scrollbar verticale du bloc central. Varie
 * selon plateforme et DPR (15 px Windows standard, 16 px Windows DPR
 * 1.5, 17 px certains cas, 0 px macOS overlay). Mesurée directement
 * sur le pane après mount via `offsetWidth - clientWidth` (plus précis
 * qu'un probe div générique · le pane peut différer de 1 px selon le
 * contexte de rendu et le DPR).
 *
 * Re-mesurée via ResizeObserver pour gérer · changement de DPR (zoom),
 * changement d'overflow (apparition/disparition de la scrollbar V
 * quand le nombre de véhicules change).
 */
const scrollbarWidth = ref(15);
const measureScrollbarWidth = (): void => {
    if (middleRef.value === null) return;
    const sw = middleRef.value.offsetWidth - middleRef.value.clientWidth;
    // Buffer de -1 px : `offsetWidth` est arrondi à l'entier supérieur
    // (1174 pour une largeur effective 1173.7 par ex.), ce qui ajoute ~
    // 0.3 px de débordement, combiné aux ~0.4 px d'arrondi flex sur les
    // 52 cellules → ~0.7 px de cellule droite clippée par le wrapper.
    // Le buffer de 1 px sacrifie 1 px (au plus) de V scrollbar visible
    // (fine ligne grise à peine perceptible) pour garantir zéro clipping
    // côté contenu, qui est plus gênant visuellement.
    if (sw > 0) {
        scrollbarWidth.value = sw - 1;
    }
};
let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    requestAnimationFrame(() => {
        measureScrollbarWidth();
        if (middleRef.value !== null && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(measureScrollbarWidth);
            resizeObserver.observe(middleRef.value);
        }
    });
});
onUnmounted(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
});

/**
 * Sync le scrollTop entre les 3 panes. On utilise l'`event.currentTarget`
 * comme source plutôt qu'une ref · plus fiable (pas de problème de Ref
 * non-unwrappé dans le template) et zéro besoin de capturer les refs
 * pour l'identification (just comparaison HTMLElement vs HTMLElement).
 *
 * La garde `el.scrollTop !== top` suffit à empêcher le feedback loop ·
 * setter scrollTop à la même valeur ne déclenche pas de nouveau scroll
 * event. Pas besoin de flag global `syncing` qui pose des problèmes de
 * stuck state en cas d'event scroll perdus.
 */
function syncFrom(e: Event): void {
    const source = e.currentTarget as HTMLElement;
    const top = source.scrollTop;
    [leftRef.value, middleRef.value, rightRef.value].forEach((el) => {
        if (el !== null && el !== source && el.scrollTop !== top) {
            el.scrollTop = top;
        }
    });
}
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
            Container heatmap · simple wrapper avec border, sans
            overflow. Les 3 blocs internes gèrent chacun leur scroll.
        -->
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex">
                <!--
                    Bloc gauche · VehicleInfo. Overflow-y auto (sync
                    avec les autres), scrollbar V masquée pour ne pas
                    doublonner avec celle du bloc droit.
                -->
                <div
                    ref="leftRef"
                    class="heatmap-pane shrink-0 max-h-[50em] overflow-y-auto bg-white scrollbar-hide-all"
                    @scroll="syncFrom"
                >
                    <!-- Header sticky · placeholder pour aligner avec les mois du centre -->
                    <div class="sticky top-0 z-10 bg-white pt-4 pb-2 pl-4 pr-3">
                        <div class="h-4" />
                    </div>
                    <!-- Body rows -->
                    <div
                        v-for="(view, idx) in vehicleViews"
                        :key="`left-${view.id}`"
                        class="pl-4 pr-3"
                        :class="idx > 0 && 'border-t border-slate-100'"
                    >
                        <VehicleInfo :vehicle-view="view" />
                    </div>
                </div>

                <!--
                    Bloc centre · technique « wrapper + negative margin »
                    pour masquer la scrollbar V tout en gardant la
                    scrollbar H visible. CSS pseudo `::-webkit-scrollbar:
                    vertical` ne fonctionne pas en `<style scoped>` Vue,
                    on opte pour cette technique 100 % CSS standard
                    cross-browser.
                    Le wrapper a `overflow-hidden` et clip la scrollbar V
                    de l'inner. Le negative margin est calculé
                    dynamiquement (`scrollbarWidth`) pour matcher
                    EXACTEMENT la largeur de la scrollbar V de la
                    plateforme · zéro buffer = zéro clipping du contenu
                    sur le bord droit (précision pixel-perfect requise
                    par l'alignement entre la grille de cellules et le
                    cadre du conteneur).
                -->
                <div class="min-w-0 flex-1 max-h-[50em] overflow-hidden bg-white">
                    <div
                        ref="middleRef"
                        class="heatmap-pane h-full overflow-auto"
                        :style="{ marginRight: `-${scrollbarWidth}px` }"
                        @scroll="syncFrom"
                    >
                        <!--
                            Header sticky · labels mensuels. `min-width`
                            au lieu de `width` pour laisser le bloc
                            s'étendre quand le conteneur est plus large
                            que la grille (grand écran). Les mois
                            utilisent `flex` avec une basis = weeks ×
                            HEATMAP_CELL_WIDTH - 1 px (le -1 correspond
                            au gap intra-mois qui devient extérieur quand
                            on découpe par mois) pour rester strictement
                            alignés avec les cellules du body, même en
                            croissance.
                        -->
                        <div
                            class="sticky top-0 z-10 bg-white pt-4 pb-2"
                            :style="{ minWidth: `${HEATMAP_GRID_WIDTH}px` }"
                        >
                            <div class="flex h-4 gap-[1px]">
                                <div
                                    v-for="month in monthLabels"
                                    :key="month.name"
                                    :style="{
                                        flex: `${month.weeks} 0 ${month.weeks * HEATMAP_CELL_WIDTH - 1}px`,
                                    }"
                                    class="text-xs font-medium text-slate-500"
                                >
                                    {{ month.name }}
                                </div>
                            </div>
                        </div>
                        <!--
                            Body rows · `min-width` au lieu de `width`
                            pour autoriser l'expansion sur grand écran.
                            Les cellules à l'intérieur (WeekCellsRow)
                            utilisent `grow` pour absorber l'espace
                            supplémentaire au prorata.
                        -->
                        <div
                            v-for="(view, idx) in vehicleViews"
                            :key="`mid-${view.id}`"
                            :style="{ minWidth: `${HEATMAP_GRID_WIDTH}px` }"
                            :class="idx > 0 && 'border-t border-slate-100'"
                        >
                            <WeekCellsRow
                                :vehicle-view="view"
                                :fiscal-year="fiscalYear"
                                @cell-click="$emit('cell-click', $event)"
                            />
                        </div>
                    </div>
                </div>

                <!--
                    Bloc droit · VehicleSummary. Overflow-y auto avec
                    scrollbar V visible · c'est la scrollbar V
                    « principale » du composant (visuellement à droite
                    du conteneur).
                -->
                <div
                    ref="rightRef"
                    class="heatmap-pane shrink-0 max-h-[50em] overflow-y-auto bg-white"
                    @scroll="syncFrom"
                >
                    <div class="sticky top-0 z-10 bg-white pt-4 pb-2 pl-3 pr-4">
                        <div class="h-4" />
                    </div>
                    <div
                        v-for="(view, idx) in vehicleViews"
                        :key="`right-${view.id}`"
                        class="pl-3 pr-4"
                        :class="idx > 0 && 'border-t border-slate-100'"
                    >
                        <VehicleSummary :vehicle-view="view" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * Masque toutes les scrollbars (V et H) · utilisé sur le bloc gauche
 * qui n'a pas de overflow horizontal de toute façon. Compatible Webkit,
 * Firefox et IE/Edge legacy.
 */
.scrollbar-hide-all {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.scrollbar-hide-all::-webkit-scrollbar {
    display: none;
}
</style>
