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

type Vehicle = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
    userType: string; // VP | VU
    energy: string;
    co2Method: string;
    co2Value: number | null;
    taxableHorsepower: number | null;
    weeks: number[]; // 52 densités
    daysTotal: number;
    annualTaxDue: number;
};

const props = defineProps<{
    vehicles: Vehicle[];
    fiscalYear: number;
}>();

const emit = defineEmits<{
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

const densityClass = (days: number): string => {
    if (days <= 0) return 'bg-white border border-slate-200';
    if (days === 1) return 'bg-blue-50';
    if (days === 2) return 'bg-blue-100';
    if (days === 3) return 'bg-blue-300';
    if (days === 4) return 'bg-blue-500';
    if (days === 5) return 'bg-blue-700';
    if (days === 6) return 'bg-blue-800';
    return 'bg-blue-900'; // 7/7
};

const textContrastClass = (days: number): string => {
    return days >= 3 ? 'text-white' : 'text-slate-500';
};

const formatEur = (value: number): string =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        maximumFractionDigits: 0,
    })
        .format(value)
        .replace(/ | /g, ' ');

const totalAnnualTax = computed((): number =>
    props.vehicles.reduce((sum, v) => sum + v.annualTaxDue, 0),
);
const totalDays = computed((): number =>
    props.vehicles.reduce((sum, v) => sum + v.daysTotal, 0),
);

// Largeur en pixels d'une cellule hebdo (20px) + gap (1px).
const CELL_WIDTH = 21;
const GRID_WIDTH = 52 * CELL_WIDTH;
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Bandeau résumé -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-1">
            <div class="flex gap-6 text-sm">
                <div>
                    <span class="text-slate-500">Flotte :</span>
                    <span class="ml-1 font-medium text-slate-900">
                        {{ vehicles.length }} véhicules
                    </span>
                </div>
                <div>
                    <span class="text-slate-500">Jours-véhicule :</span>
                    <span class="ml-1 font-medium text-slate-900">
                        {{ totalDays.toLocaleString('fr-FR') }}
                    </span>
                </div>
                <div>
                    <span class="text-slate-500">Taxes totales {{ fiscalYear }} :</span>
                    <span class="ml-1 font-mono font-medium text-slate-900">
                        {{ formatEur(totalAnnualTax) }}
                    </span>
                </div>
            </div>

            <!-- Légende densité -->
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>0 j</span>
                <div class="flex gap-0.5">
                    <span
                        v-for="n in 8"
                        :key="n"
                        :class="[densityClass(n - 1), 'h-4 w-4 rounded-[3px]']"
                    />
                </div>
                <span>7 j / 7</span>
            </div>
        </div>

        <!-- Container heatmap : 3 zones (fixe / scrollable / fixe) -->
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex items-stretch">
                <!-- ZONE GAUCHE FIXE — mini-fiches véhicules -->
                <div class="shrink-0 bg-white pr-3">
                    <!-- Spacer aligné sur les labels mensuels -->
                    <div class="mb-2 h-4" />
                    <div class="flex flex-col">
                        <div
                            v-for="vehicle in vehicles"
                            :key="`left-${vehicle.id}`"
                            class="flex h-[40px] items-center gap-2 border-t border-slate-100 first:border-t-0"
                        >
                            <span
                                :class="[
                                    'rounded-[3px] px-1 py-0.5 font-mono text-[10px] font-semibold uppercase',
                                    vehicle.userType === 'VU'
                                        ? 'bg-amber-50 text-amber-700'
                                        : 'bg-slate-100 text-slate-600',
                                ]"
                            >
                                {{ vehicle.userType }}
                            </span>
                            <div class="min-w-0 w-[200px]">
                                <p
                                    class="truncate font-mono text-xs font-medium text-slate-900"
                                >
                                    {{ vehicle.licensePlate }}
                                </p>
                                <p class="truncate text-[11px] text-slate-500">
                                    {{ vehicle.brand }} {{ vehicle.model }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZONE CENTRE SCROLLABLE — 52 semaines -->
                <div class="min-w-0 flex-1 overflow-x-auto">
                    <div :style="{ width: `${GRID_WIDTH}px` }">
                        <!-- Labels mensuels -->
                        <div class="mb-2 flex h-4">
                            <div
                                v-for="month in monthLabels"
                                :key="month.name"
                                :style="{ width: `${month.weeks * CELL_WIDTH}px` }"
                                class="text-xs font-medium text-slate-500"
                            >
                                {{ month.name }}
                            </div>
                        </div>

                        <!-- Lignes cellules -->
                        <div class="flex flex-col">
                            <div
                                v-for="vehicle in vehicles"
                                :key="`cells-${vehicle.id}`"
                                class="flex h-[40px] items-center gap-[1px] border-t border-slate-100 first:border-t-0"
                            >
                                <button
                                    v-for="(days, weekIndex) in vehicle.weeks"
                                    :key="weekIndex"
                                    type="button"
                                    :class="[
                                        densityClass(days),
                                        textContrastClass(days),
                                        'flex h-7 w-5 items-center justify-center rounded-[3px] font-mono text-[9px] transition-opacity duration-[120ms] ease-out hover:opacity-70',
                                    ]"
                                    :aria-label="`Semaine ${weekIndex + 1} · ${vehicle.licensePlate} · ${days} jours utilisés`"
                                    :title="`S${weekIndex + 1} · ${days}j / 7`"
                                    @click="
                                        emit('cell-click', {
                                            vehicleId: vehicle.id,
                                            week: weekIndex + 1,
                                        })
                                    "
                                >
                                    {{ days > 0 ? days : '' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZONE DROITE FIXE — taxe annuelle + jours total -->
                <div class="shrink-0 bg-white pl-3">
                    <div class="mb-2 h-4" />
                    <div class="flex flex-col">
                        <div
                            v-for="vehicle in vehicles"
                            :key="`right-${vehicle.id}`"
                            class="flex h-[40px] items-center justify-end border-t border-slate-100 text-right first:border-t-0"
                        >
                            <div>
                                <p
                                    class="font-mono text-xs font-medium text-slate-900"
                                >
                                    {{ formatEur(vehicle.annualTaxDue) }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ vehicle.daysTotal }} j
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
