<script setup lang="ts">
/**
 * Skeleton du graphique Évolution · 3 barres rectangulaires avec
 * hauteurs en pixels explicites (les % posaient problème car le parent
 * flex n'avait pas de hauteur calculable). Layout mimétique de
 * Chart.js · Y-axis labels à gauche (50px), zone bars 240px, X-axis
 * labels 30px = 280px total.
 *
 * Barres : 210/150/100px (hauteurs variées · plafond 210 sur 240 ·
 * laisse espace top pour mimer l'axe Y > max data value). Largeur 80%
 * de chaque cellule (= `categoryPercentage × barPercentage` Chart.js).
 *
 * Sert au fallback initial du mount (`<Deferred data="historyJoursVehicule">`)
 * ET au shimmer interne du graphique lors du clic sur un onglet
 * `Inertia::optional` non-encore-chargé.
 */
import Skeleton from '@/Components/Ui/Skeleton/Skeleton.vue';

defineProps<{
    /** Si true · n'affiche que la zone chart (le header reste celui du composant parent). */
    chartOnly?: boolean;
}>();

const BARS: readonly { heightPx: number }[] = [
    { heightPx: 210 },
    { heightPx: 150 },
    { heightPx: 100 },
];
</script>

<template>
    <div v-if="chartOnly" class="flex h-[280px]" aria-busy="true">
        <!-- Colonne Y-axis labels (6 ticks alignés verticalement) -->
        <div class="flex w-[50px] shrink-0 flex-col justify-between py-1 pr-3">
            <Skeleton v-for="i in 6" :key="i" class="h-2 w-full rounded" />
        </div>
        <!-- Zone chart · 3 bars (240px) + X-axis labels (30px = pt-2 + h-2.5) -->
        <div class="flex flex-1 flex-col">
            <div class="flex h-[240px] items-end">
                <div
                    v-for="(bar, i) in BARS"
                    :key="i"
                    class="flex flex-1 justify-center"
                >
                    <Skeleton
                        class="w-[80%] rounded-sm"
                        :style="{ height: bar.heightPx + 'px' }"
                    />
                </div>
            </div>
            <div class="flex pt-2">
                <div
                    v-for="(_, i) in BARS"
                    :key="i"
                    class="flex flex-1 justify-center"
                >
                    <Skeleton class="h-2.5 w-12 rounded" />
                </div>
            </div>
        </div>
    </div>

    <div
        v-else
        class="rounded-xl border border-slate-200 bg-white p-6"
        aria-busy="true"
    >
        <!-- Header · titre + sous-titre + 4 onglets pill -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-2">
                <Skeleton class="h-4 w-44 rounded" />
                <Skeleton class="h-3 w-60 rounded" />
            </div>
            <div class="flex gap-1.5 rounded-lg border border-slate-200 bg-white p-1">
                <Skeleton v-for="i in 4" :key="i" class="h-6 w-20 rounded-md" />
            </div>
        </div>
        <!-- Zone chart 280px · layout identique chart.js -->
        <div class="flex h-[280px]">
            <div class="flex w-[50px] shrink-0 flex-col justify-between py-1 pr-3">
                <Skeleton v-for="i in 6" :key="i" class="h-2 w-full rounded" />
            </div>
            <div class="flex flex-1 flex-col">
                <div class="flex h-[240px] items-end">
                    <div
                        v-for="(bar, i) in BARS"
                        :key="i"
                        class="flex flex-1 justify-center"
                    >
                        <Skeleton
                            class="w-[80%] rounded-sm"
                            :style="{ height: bar.heightPx + 'px' }"
                        />
                    </div>
                </div>
                <div class="flex pt-2">
                    <div
                        v-for="(_, i) in BARS"
                        :key="i"
                        class="flex flex-1 justify-center"
                    >
                        <Skeleton class="h-2.5 w-12 rounded" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
